<?php

namespace App\Services\V2;

use App\Mail\EventCancellationMail;
use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Invitation-aware delete/cancel for recurring (and propagated) event instances.
 *
 * - No accepted venue/talent/organiser invitation → soft-delete
 * - Accepted invitation exists → status=cancelled + notify after commit
 */
class EventInstanceDispositionService
{
    /** @var list<array{event_id: int, actor_id: int}> */
    private array $pendingNotifications = [];

    public function __construct(
        private readonly EventService $eventService,
        private readonly FirebaseNotificationService $firebaseNotificationService,
    ) {}

    /**
     * @param  iterable<EventV2>  $events
     * @return array{deleted: int, cancelled: int, skipped_already_handled: int, notified: int}
     */
    public function disposeMany(iterable $events, User $actor, string $context): array
    {
        $stats = $this->emptyStats();
        $collection = $events instanceof Collection ? $events : collect($events);

        if ($collection->isEmpty()) {
            return $stats;
        }

        $acceptedIds = $this->acceptedInvitationEventIds(
            $collection->pluck('id')->map(fn ($id) => (int) $id)->all()
        );

        foreach ($collection as $event) {
            $this->disposeOne($event, $actor, $context, $stats, $acceptedIds);
        }

        $this->scheduleNotificationsAfterCommit();

        return $stats;
    }

    /**
     * @param  array{deleted: int, cancelled: int, skipped_already_handled: int, notified: int}  $stats
     * @param  array<int, true>  $acceptedEventIds
     */
    public function disposeOne(
        EventV2 $event,
        User $actor,
        string $context,
        array &$stats,
        ?array $acceptedEventIds = null,
    ): void {
        if ($event->trashed() || $event->status === EventV2::STATUS_CANCELLED) {
            $stats['skipped_already_handled']++;

            return;
        }

        $acceptedIds = $acceptedEventIds ?? $this->acceptedInvitationEventIds([(int) $event->id]);

        if (isset($acceptedIds[$event->id])) {
            $this->eventService->cancelOccurrence($event, $actor);
            $inviteCount = EventInvitation::query()
                ->where('event_id', $event->id)
                ->where('status', EventInvitation::STATUS_ACCEPTED)
                ->whereIn('receiver_type', [
                    EventInvitation::TYPE_TALENT,
                    EventInvitation::TYPE_ORGANISER,
                    EventInvitation::TYPE_VENUE,
                ])
                ->count();
            $this->queueInviteeNotifications($event, $actor);
            $stats['cancelled']++;
            $stats['notified'] += $inviteCount;

            Log::info('Event instance cancelled (accepted invitations)', [
                'context' => $context,
                'event_id' => $event->id,
                'series_id' => $event->series_id,
                'actor_id' => $actor->id,
            ]);

            return;
        }

        $this->eventService->deleteWithoutTransaction($event);
        $stats['deleted']++;

        Log::info('Event instance deleted (no accepted invitations)', [
            'context' => $context,
            'event_id' => $event->id,
            'series_id' => $event->series_id,
            'actor_id' => $actor->id,
        ]);
    }

    /**
     * @param  list<int>  $eventIds
     * @return array<int, true>
     */
    public function acceptedInvitationEventIds(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        return EventInvitation::query()
            ->whereIn('event_id', $eventIds)
            ->where('status', EventInvitation::STATUS_ACCEPTED)
            ->whereIn('receiver_type', [
                EventInvitation::TYPE_TALENT,
                EventInvitation::TYPE_ORGANISER,
                EventInvitation::TYPE_VENUE,
            ])
            ->distinct()
            ->pluck('event_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    public function hasAcceptedInvitations(EventV2 $event): bool
    {
        return isset($this->acceptedInvitationEventIds([(int) $event->id])[$event->id]);
    }

    private function queueInviteeNotifications(EventV2 $event, User $actor): void
    {
        $this->pendingNotifications[] = [
            'event_id' => (int) $event->id,
            'actor_id' => (int) $actor->id,
        ];
    }

    private function scheduleNotificationsAfterCommit(): void
    {
        if ($this->pendingNotifications === []) {
            return;
        }

        $batch = $this->pendingNotifications;
        $this->pendingNotifications = [];

        DB::afterCommit(function () use ($batch): void {
            foreach ($batch as $item) {
                $this->sendInviteeNotifications($item['event_id'], $item['actor_id']);
            }
        });
    }

    private function sendInviteeNotifications(int $eventId, int $actorId): void
    {
        $event = EventV2::with('user')->find($eventId);
        $actor = User::find($actorId);

        if (! $event || ! $actor) {
            return;
        }

        $invitations = EventInvitation::query()
            ->where('event_id', $event->id)
            ->where('status', EventInvitation::STATUS_ACCEPTED)
            ->whereIn('receiver_type', [
                EventInvitation::TYPE_TALENT,
                EventInvitation::TYPE_ORGANISER,
                EventInvitation::TYPE_VENUE,
            ])
            ->with(['receiver', 'event'])
            ->get();

        foreach ($invitations as $invitation) {
            $this->firebaseNotificationService->createEventCancellationNotification(
                $event,
                $invitation,
                $actor
            );

            $receiver = $invitation->receiver;
            if ($receiver?->email) {
                Mail::to($receiver->email)->send(new EventCancellationMail($event, $invitation, $actor));
            }
        }
    }

    /**
     * @return array{deleted: int, cancelled: int, skipped_already_handled: int, notified: int}
     */
    private function emptyStats(): array
    {
        return [
            'deleted' => 0,
            'cancelled' => 0,
            'skipped_already_handled' => 0,
            'notified' => 0,
        ];
    }
}
