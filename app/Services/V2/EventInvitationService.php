<?php

namespace App\Services\V2;

use App\Http\Resources\V2\EventResource;
use App\Mail\EventInvitationMail;
use App\Models\EventInvitation;
use App\Models\EventInvitationLog;
use App\Models\EventV2;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EventInvitationService
{
    public function __construct(
        private readonly FirebaseNotificationService $firebaseNotificationService,
        private readonly EventInvitedEntitiesService $eventInvitedEntitiesService
    ) {}

    /**
     * Create invitations for all invited users when an event is created/updated.
     */
    public function createInvitationsForEvent(EventV2 $event, User $sender): array
    {
        $created = [];
        $skipped = [];

        $invitedTalents = $event->invited_talents ?? [];
        $invitedOrganisers = $event->invited_organisers ?? [];
        $invitedVenues = $event->invited_venues ?? [];

        Log::info('Creating invitations for event', [
            'event_id' => $event->id,
            'invited_talents' => $invitedTalents,
            'invited_organisers' => $invitedOrganisers,
            'invited_venues' => $invitedVenues,
        ]);

        DB::transaction(function () use ($event, $sender, &$created, &$skipped, $invitedTalents, $invitedOrganisers, $invitedVenues) {

            foreach ($invitedTalents as $userId) {
                $result = $this->createInvitation($event, $sender, (int) $userId, EventInvitation::TYPE_TALENT);
                if ($result) {
                    $created[] = $result;
                } else {
                    $skipped[] = ['user_id' => $userId, 'type' => 'talent'];
                }
            }

            foreach ($invitedOrganisers as $userId) {
                $result = $this->createInvitation($event, $sender, (int) $userId, EventInvitation::TYPE_ORGANISER);
                if ($result) {
                    $created[] = $result;
                } else {
                    $skipped[] = ['user_id' => $userId, 'type' => 'organiser'];
                }
            }

            foreach ($invitedVenues as $userId) {
                $result = $this->createInvitation($event, $sender, (int) $userId, EventInvitation::TYPE_VENUE);
                if ($result) {
                    $created[] = $result;
                } else {
                    $skipped[] = ['user_id' => $userId, 'type' => 'venue'];
                }
            }
        });

        foreach ($created as $invitation) {
            $this->sendInvitationEmail($invitation);
            $this->pushInvitationNotification($invitation);
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    /**
     * Generate a secure invitation token (64 chars).
     */
    public function generateInvitationToken(): string
    {
        return Str::random(64);
    }

    /**
     * Create a single invitation with token.
     */
    public function createInvitation(
        EventV2 $event,
        User $sender,
        int $receiverId,
        string $receiverType
    ): ?EventInvitation {
        $receiver = User::find($receiverId);
        if (!$receiver) {
            Log::warning('Invitation skipped: receiver not found', [
                'event_id' => $event->id,
                'receiver_id' => $receiverId,
            ]);
            return null;
        }

        if ($receiver->id === $sender->id) {
            Log::info('Invitation skipped: cannot invite yourself', ['receiver_id' => $receiverId]);
            return null;
        }

        $existing = EventInvitation::where('event_id', $event->id)
            ->where('receiver_id', $receiverId)
            ->first();

        if ($existing) {
            Log::info('Invitation skipped: already exists', [
                'event_id' => $event->id,
                'receiver_id' => $receiverId,
                'existing_invitation_id' => $existing->id,
            ]);
            return null;
        }

        $token = $this->generateInvitationToken();
        $expiresAt = now()->addHours(EventInvitation::TOKEN_EXPIRY_HOURS);

        $invitation = EventInvitation::create([
            'event_id' => $event->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiverId,
            'receiver_type' => $receiverType,
            'status' => EventInvitation::STATUS_PENDING,
            'invitation_token' => $token,
            'token_expires_at' => $expiresAt,
        ]);

        EventInvitationLog::log($invitation, EventInvitationLog::ACTION_CREATED, $sender->id);

        Log::info('Invitation created', [
            'invitation_id' => $invitation->id,
            'event_id' => $event->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiverId,
            'receiver_type' => $receiverType,
        ]);

        return $invitation;
    }

    /**
     * Send invitation email.
     * Uses send() (not queue) so emails are delivered immediately without requiring a queue worker.
     */
    public function sendInvitationEmail(EventInvitation $invitation): void
    {
        $receiver = $invitation->receiver;

        if (!$receiver || !$receiver->email) {
            Log::warning('Invitation email skipped: receiver has no email', [
                'invitation_id' => $invitation->id,
                'receiver_id' => $invitation->receiver_id,
            ]);
            return;
        }

        try {
            Mail::to($receiver->email)->send(new EventInvitationMail($invitation));

            EventInvitationLog::log($invitation, EventInvitationLog::ACTION_EMAIL_SENT, $invitation->sender_id);

            Log::info('Invitation email sent', [
                'invitation_id' => $invitation->id,
                'receiver_email' => $receiver->email,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send invitation email', [
                'invitation_id' => $invitation->id,
                'receiver_email' => $receiver->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Respond to an invitation (accept or reject).
     * Supports authenticated user (receiver) OR token-based validation.
     */
    public function respond(EventInvitation $invitation, string $status, ?User $user = null, ?string $token = null): EventInvitation
    {
        $authenticated = $user !== null;

        if ($authenticated) {
            if ($invitation->receiver_id !== $user->id) {
                throw new \InvalidArgumentException('You are not authorized to respond to this invitation.');
            }
        } else {
            if (empty($token)) {
                throw new \InvalidArgumentException('Token is required to respond to this invitation.');
            }
            if (!$invitation->isTokenValid($token)) {
                if ($invitation->isTokenExpired()) {
                    throw new \InvalidArgumentException('This invitation link has expired.');
                }
                throw new \InvalidArgumentException('Invalid invitation token.');
            }
        }

        if (!$invitation->isPending()) {
            throw new \InvalidArgumentException('This invitation has already been responded to.');
        }

        if (!in_array($status, [EventInvitation::STATUS_ACCEPTED, EventInvitation::STATUS_REJECTED])) {
            throw new \InvalidArgumentException('Invalid status. Must be accepted or rejected.');
        }

        $invitation->update([
            'status' => $status,
            'responded_at' => now(),
        ]);

        $receiver = $invitation->receiver;
        $this->markInvitationNotificationCompleted($invitation->id, (string) $receiver->id);
        $action = $status === EventInvitation::STATUS_ACCEPTED
            ? EventInvitationLog::ACTION_ACCEPTED
            : EventInvitationLog::ACTION_REJECTED;

        EventInvitationLog::log($invitation, $action, $receiver->id);

        Log::info('Invitation responded', [
            'invitation_id' => $invitation->id,
            'status' => $status,
            'receiver_id' => $receiver->id,
        ]);

        return $invitation->fresh(['event', 'sender', 'receiver']);
    }

    /**
     * Get invitations for a user (non-paginated; prefer {@see paginateReceivedInvitations} for API).
     */
    public function getUserInvitations(User $user, ?string $status = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = EventInvitation::with(['event', 'sender'])
            ->where('receiver_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * Paginated invitations for the receiver with eager-loaded event graph and hydrated invite payloads.
     *
     * @param  array{status?: string|null, event_timing?: string|null, per_page?: int|null, page?: int|null}  $filters
     */
    public function paginateReceivedInvitations(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $query = EventInvitation::query()
            ->with([
                'sender:id,name,email',
                'event' => function ($q) {
                    $q->with(['category', 'venue', 'organisers', 'talents', 'user']);
                },
            ])
            ->where('receiver_id', $user->id)
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['event_timing'])) {
            $this->applyReceivedInvitationEventTimingFilter($query, $filters['event_timing']);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', (int) ($filters['page'] ?? 1));

        foreach ($paginator->getCollection() as $invitation) {
            $event = $invitation->event;
            if ($event) {
                $event->setRelation('subcategories', $event->subcategories_from_ids);
            }
        }

        $events = $paginator->getCollection()->map(fn (EventInvitation $i) => $i->event)->filter()->values()->all();
        $this->eventInvitedEntitiesService->hydrate($events);

        return $paginator;
    }

    /**
     * Load related models and hydrated invite payloads for API responses after accept/reject.
     */
    public function decorateInvitationForDetailResponse(EventInvitation $invitation): EventInvitation
    {
        $invitation->loadMissing([
            'sender:id,name,email',
            'event' => fn ($q) => $q->with(['category', 'venue', 'organisers', 'talents', 'user']),
        ]);

        if ($invitation->event) {
            $invitation->event->setRelation('subcategories', $invitation->event->subcategories_from_ids);
            $this->eventInvitedEntitiesService->hydrate([$invitation->event]);
        }

        return $invitation;
    }

    /**
     * Upcoming {@see EventV2} rows from accepted invitations where {@see EventInvitation::$receiver_type}
     * matches the profile role (talent / organiser / venue) and {@see EventInvitation::$receiver_id} is the profile owner's user id.
     *
     * @return list<array<string, mixed>>
     */
    public function upcomingAcceptedEventsPayloadForProfileUser(int $receiverUserId, string $receiverType): array
    {
        if (! in_array($receiverType, EventInvitation::TYPES, true)) {
            return [];
        }

        $events = $this->queryUpcomingAcceptedInvitationEvents($receiverUserId, $receiverType)->all();

        $this->prepareEventsForProfilePayload($events);

        return EventResource::collection($events)->toArray(request());
    }

    /**
     * Batch-attach {@see EventInvitation::$receiver_type}-matched upcoming events onto V2 profiles for API resources
     * (uses attribute {@code _upcoming_events} — see {@see \App\Http\Resources\V2\TalentResource}).
     *
     * @param  iterable<int, Model>  $profiles  {@see TalentV2}|{@see \App\Models\OrganiserV2}|{@see \App\Models\VenueV2}
     */
    public function hydrateUpcomingAcceptedInvitationEventsOnProfiles(iterable $profiles, string $receiverType): void
    {
        if (! in_array($receiverType, EventInvitation::TYPES, true)) {
            return;
        }

        $profiles = collect($profiles)->values();
        if ($profiles->isEmpty()) {
            return;
        }

        $withFlag = $profiles->filter(fn (Model $p) => (bool) ($p->getAttribute('show_upcoming_events') ?? false));
        if ($withFlag->isEmpty()) {
            return;
        }

        $userIds = $withFlag->pluck('user_id')->unique()->filter()->map(fn ($id) => (int) $id)->values()->all();
        if ($userIds === []) {
            return;
        }

        $invitations = $this->baseUpcomingAcceptedInvitationsQuery($receiverType)
            ->whereIn('receiver_id', $userIds)
            ->orderByDesc('created_at')
            ->get();

        $grouped = $invitations->groupBy(fn (EventInvitation $i) => (int) $i->receiver_id);

        $eventsByUserId = [];
        foreach ($userIds as $uid) {
            $group = $grouped->get($uid, collect());
            $eventsByUserId[$uid] = $this->uniqueSortedEventsFromInvitations($group)->all();
        }

        $flatUnique = collect($eventsByUserId)->flatten(1)->unique('id')->values()->all();
        $this->prepareEventsForProfilePayload($flatUnique);

        foreach ($withFlag as $profile) {
            $uid = (int) $profile->getAttribute('user_id');
            $profile->setAttribute('_upcoming_events', $eventsByUserId[$uid] ?? []);
        }
    }

    /**
     * @return Collection<int, EventV2>
     */
    private function queryUpcomingAcceptedInvitationEvents(int $receiverUserId, string $receiverType): Collection
    {
        $invitations = $this->baseUpcomingAcceptedInvitationsQuery($receiverType)
            ->where('receiver_id', $receiverUserId)
            ->orderByDesc('created_at')
            ->get();

        return $this->uniqueSortedEventsFromInvitations($invitations);
    }

    /**
     * @return Builder<\App\Models\EventInvitation>
     */
    private function baseUpcomingAcceptedInvitationsQuery(string $receiverType): Builder
    {
        $now = Carbon::now();

        return EventInvitation::query()
            ->with([
                'event' => fn ($q) => $q->with(['category', 'venue', 'organisers', 'talents', 'user']),
            ])
            ->accepted()
            ->where('receiver_type', $receiverType)
            ->whereHas('event', function (Builder $q) use ($now) {
                $this->whereEventEffectiveEndIsAfter($q, $now);
            });
    }

    /**
     * @param  Collection<int, EventInvitation>  $invitations
     * @return Collection<int, EventV2>
     */
    private function uniqueSortedEventsFromInvitations(Collection $invitations): Collection
    {
        return $invitations->map->event
            ->filter()
            ->unique('id')
            ->sortBy(function (?EventV2 $e) {
                if (! $e instanceof EventV2) {
                    return '';
                }
                $d = $e->event_date?->format('Y-m-d') ?? '0000-00-00';

                return $d.' '.($e->start_time ?? '00:00:00');
            })
            ->values();
    }

    /**
     * @param  array<int, EventV2>  $events
     */
    private function prepareEventsForProfilePayload(array $events): void
    {
        if ($events === []) {
            return;
        }

        foreach ($events as $event) {
            if ($event instanceof EventV2) {
                $event->setRelation('subcategories', $event->subcategories_from_ids);
            }
        }

        $this->eventInvitedEntitiesService->hydrate($events);
    }

    /**
     * @param  Builder<\App\Models\EventInvitation>  $query
     */
    private function applyReceivedInvitationEventTimingFilter(Builder $query, string $timing): void
    {
        $now = Carbon::now();
        $query->whereHas('event', function (Builder $q) use ($timing, $now) {
            if ($timing === 'upcoming') {
                $this->whereEventEffectiveEndIsAfter($q, $now);
            } elseif ($timing === 'past') {
                $this->whereEventEffectiveEndIsOnOrBefore($q, $now);
            }
        });
    }

    /**
     * @param  Builder<\App\Models\EventV2>  $q
     */
    private function whereEventEffectiveEndIsAfter(Builder $q, Carbon $moment): void
    {
        $table = $q->getModel()->getTable();
        $m = $moment->format('Y-m-d H:i:s');
        $q->where(function (Builder $outer) use ($table, $m) {
            $outer->whereNotNull("{$table}.end_datetime")
                ->where("{$table}.end_datetime", '>', $m)
                ->orWhere(function (Builder $inner) use ($table, $m) {
                    $inner->whereNull("{$table}.end_datetime");
                    $this->applyFallbackEndComparedToMoment($inner, $table, $m, '>');
                });
        });
    }

    /**
     * @param  Builder<\App\Models\EventV2>  $q
     */
    private function whereEventEffectiveEndIsOnOrBefore(Builder $q, Carbon $moment): void
    {
        $table = $q->getModel()->getTable();
        $m = $moment->format('Y-m-d H:i:s');
        $q->where(function (Builder $outer) use ($table, $m) {
            $outer->whereNotNull("{$table}.end_datetime")
                ->where("{$table}.end_datetime", '<=', $m)
                ->orWhere(function (Builder $inner) use ($table, $m) {
                    $inner->whereNull("{$table}.end_datetime");
                    $this->applyFallbackEndComparedToMoment($inner, $table, $m, '<=');
                });
        });
    }

    /**
     * @param  Builder<\App\Models\EventV2>  $q
     */
    private function applyFallbackEndComparedToMoment(Builder $q, string $table, string $moment, string $operator): void
    {
        $driver = $q->getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $q->whereRaw(
                "TIMESTAMP({$table}.event_date, COALESCE({$table}.end_time, '23:59:59')) {$operator} ?",
                [$moment]
            );

            return;
        }

        if ($driver === 'pgsql') {
            $q->whereRaw(
                "({$table}.event_date + COALESCE({$table}.end_time::time, TIME '23:59:59')) {$operator} ?::timestamp",
                [$moment]
            );

            return;
        }

        // sqlite and others
        $q->whereRaw(
            "({$table}.event_date || ' ' || COALESCE({$table}.end_time, '23:59:59')) {$operator} ?",
            [$moment]
        );
    }

    /**
     * Get invitations for an event.
     */
    public function getEventInvitations(EventV2 $event): \Illuminate\Database\Eloquent\Collection
    {
        return EventInvitation::with(['receiver', 'sender'])
            ->where('event_id', $event->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Cancel/delete an invitation (only sender or event owner can do this).
     */
    public function cancelInvitation(EventInvitation $invitation, User $user): void
    {
        $event = $invitation->event;

        if ($invitation->sender_id !== $user->id && $event->user_id !== $user->id && !$user->isAdmin()) {
            throw new \InvalidArgumentException('You are not authorized to cancel this invitation.');
        }

        $invitation->delete();

        Log::info('Invitation cancelled', [
            'invitation_id' => $invitation->id,
            'cancelled_by' => $user->id,
        ]);
    }

    /**
     * Resend invitation email (regenerates token).
     */
    public function resendInvitation(EventInvitation $invitation, User $user): EventInvitation
    {
        if ($invitation->sender_id !== $user->id && $invitation->event->user_id !== $user->id && !$user->isAdmin()) {
            throw new \InvalidArgumentException('You are not authorized to resend this invitation.');
        }

        if (!$invitation->isPending()) {
            throw new \InvalidArgumentException('Cannot resend invitation that has already been responded to.');
        }

        $invitation->update([
            'invitation_token' => $this->generateInvitationToken(),
            'token_expires_at' => now()->addHours(EventInvitation::TOKEN_EXPIRY_HOURS),
        ]);

        $this->sendInvitationEmail($invitation->fresh());

        Log::info('Invitation resent', [
            'invitation_id' => $invitation->id,
            'resent_by' => $user->id,
        ]);

        return $invitation->fresh();
    }

    /**
     * Push a real-time Firestore notification to the invited user.
     */
    private function pushInvitationNotification(EventInvitation $invitation): void
    {
        try {
            $this->firebaseNotificationService->createInvitationNotification($invitation);
        } catch (\Throwable $e) {
            Log::warning('Failed to push invitation notification to Firestore', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark the Firestore invitation notification as completed for the receiver.
     */
    private function markInvitationNotificationCompleted(int $invitationId, string $receiverId): void
    {
        try {
            $this->firebaseNotificationService->markInvitationNotificationCompleted($invitationId, $receiverId);
        } catch (\Throwable $e) {
            Log::warning('Failed to mark invitation notification completed', [
                'invitation_id' => $invitationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
