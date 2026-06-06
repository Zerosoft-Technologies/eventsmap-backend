<?php

namespace App\Services\V2;

use App\Exceptions\GuestInvitationException;
use App\Mail\GuestEventInvitationMail;
use App\Models\EventInvitation;
use App\Models\EventInvitationLog;
use App\Models\EventV2;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class GuestInvitationService
{
    public function __construct(
        private readonly EventInvitationService $eventInvitationService,
    ) {}

    public function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public function userExists(string $email): bool
    {
        $normalized = $this->normalizeEmail($email);

        return User::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->exists();
    }

    /**
     * @return array{code: string, message: string, can_invite: bool}
     */
    public function validateEmailForEvent(EventV2 $event, string $email, string $receiverType): array
    {
        $email = $this->normalizeEmail($email);

        if (! in_array($receiverType, EventInvitation::TYPES, true)) {
            return [
                'code' => 'INVALID_ROLE',
                'message' => 'Invalid invitation role.',
                'can_invite' => false,
            ];
        }

        if ($this->userExists($email)) {
            return [
                'code' => 'USER_EXISTS',
                'message' => 'User already exists in the system.',
                'can_invite' => false,
            ];
        }

        if ($this->hasPendingGuestInvitation($event->id, $email, $receiverType)) {
            return [
                'code' => 'INVITATION_PENDING',
                'message' => 'Invitation already sent.',
                'can_invite' => false,
            ];
        }

        return [
            'code' => 'OK',
            'message' => 'Email is available for invitation.',
            'can_invite' => true,
        ];
    }

    public function hasPendingGuestInvitation(int $eventId, string $email, string $receiverType): bool
    {
        $email = $this->normalizeEmail($email);

        return EventInvitation::query()
            ->where('event_id', $eventId)
            ->where('receiver_type', $receiverType)
            ->where('status', EventInvitation::STATUS_PENDING)
            ->whereNull('receiver_id')
            ->whereRaw('LOWER(invitee_email) = ?', [$email])
            ->where(function ($q) {
                $q->whereNull('token_expires_at')
                    ->orWhere('token_expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * @throws GuestInvitationException
     */
    public function inviteByEmail(
        EventV2 $event,
        User $sender,
        string $email,
        string $receiverType,
        ?string $name = null,
    ): EventInvitation {
        $email = $this->normalizeEmail($email);
        $check = $this->validateEmailForEvent($event, $email, $receiverType);

        if ($check['code'] !== 'OK') {
            throw new GuestInvitationException($check['code'], $check['message']);
        }

        if ($sender->id === null) {
            throw new GuestInvitationException('UNAUTHORIZED', 'Invalid sender.');
        }

        $token = $this->eventInvitationService->generateInvitationToken();
        $expiresAt = now()->addDays(EventInvitation::GUEST_TOKEN_EXPIRY_DAYS);

        $invitation = DB::transaction(function () use ($event, $sender, $email, $receiverType, $name, $token, $expiresAt) {
            return EventInvitation::create([
                'event_id' => $event->id,
                'sender_id' => $sender->id,
                'receiver_id' => null,
                'invitee_email' => $email,
                'invitee_name' => $name ? trim($name) : null,
                'receiver_type' => $receiverType,
                'status' => EventInvitation::STATUS_PENDING,
                'invitation_token' => $token,
                'token_expires_at' => $expiresAt,
            ]);
        });

        EventInvitationLog::log($invitation, EventInvitationLog::ACTION_CREATED, $sender->id);

        $this->sendGuestInvitationEmail($invitation);

        Log::info('Guest invitation created', [
            'invitation_id' => $invitation->id,
            'event_id' => $event->id,
            'invitee_email' => $email,
            'receiver_type' => $receiverType,
        ]);

        return $invitation->fresh(['event', 'sender']);
    }

    public function sendGuestInvitationEmail(EventInvitation $invitation): void
    {
        $email = $invitation->resolvedInviteeEmail();

        if (! $email) {
            Log::warning('Guest invitation email skipped: no email', ['invitation_id' => $invitation->id]);

            return;
        }

        Mail::to($email)->send(new GuestEventInvitationMail($invitation));

        EventInvitationLog::log($invitation, EventInvitationLog::ACTION_EMAIL_SENT, $invitation->sender_id);
    }

    /**
     * Public token lookup for registration prefill.
     *
     * @return array{valid: bool, email?: string, receiver_type?: string, event_title?: string, invitation_id?: int, message?: string}
     */
    public function resolveTokenForRegistration(string $token): array
    {
        $invitation = EventInvitation::query()
            ->with(['event:id,title'])
            ->where('invitation_token', $token)
            ->whereNull('receiver_id')
            ->first();

        if (! $invitation) {
            return ['valid' => false, 'message' => 'Invalid invitation link.'];
        }

        if ($invitation->isTokenExpired()) {
            return ['valid' => false, 'message' => 'This invitation has expired.'];
        }

        if (! $invitation->isPending()) {
            return ['valid' => false, 'message' => 'This invitation is no longer available.'];
        }

        return [
            'valid' => true,
            'email' => $invitation->invitee_email,
            'name' => $invitation->invitee_name,
            'receiver_type' => $invitation->receiver_type,
            'event_title' => $invitation->event?->title,
            'invitation_id' => $invitation->id,
        ];
    }

    /**
     * After registration, link guest invitation to the new user and mark accepted.
     */
    public function acceptInvitationAfterRegistration(User $user, string $token): ?EventInvitation
    {
        $invitation = EventInvitation::query()
            ->where('invitation_token', $token)
            ->whereNull('receiver_id')
            ->pending()
            ->first();

        if (! $invitation || ! $invitation->isTokenValid($token)) {
            return null;
        }

        $email = $this->normalizeEmail($user->email);
        if ($invitation->invitee_email && $this->normalizeEmail($invitation->invitee_email) !== $email) {
            Log::warning('Guest invitation email mismatch on registration', [
                'invitation_id' => $invitation->id,
                'user_id' => $user->id,
            ]);

            return null;
        }

        $invitation->update([
            'receiver_id' => $user->id,
            'status' => EventInvitation::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);

        EventInvitationLog::log($invitation, EventInvitationLog::ACTION_ACCEPTED, $user->id);

        return $invitation->fresh(['event', 'sender']);
    }

    public function registrationProfileTypeForRole(string $receiverType): string
    {
        return match ($receiverType) {
            EventInvitation::TYPE_TALENT => User::PROFILE_TALENT,
            EventInvitation::TYPE_ORGANISER => User::PROFILE_ORGANIZER,
            EventInvitation::TYPE_VENUE => User::PROFILE_VENUE,
            default => User::PROFILE_EVENT,
        };
    }

    public function registrationUrl(EventInvitation $invitation): string
    {
        $frontend = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $token = $invitation->invitation_token;

        return "{$frontend}/invitation/{$token}";
    }
}
