<?php

namespace App\Services\V2;

use App\Mail\EventInvitationMail;
use App\Models\EventInvitation;
use App\Models\EventInvitationLog;
use App\Models\EventV2;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EventInvitationService
{
    /**
     * Create invitations for all invited users when an event is created/updated.
     */
    public function createInvitationsForEvent(EventV2 $event, User $sender): array
    {
        $created = [];
        $skipped = [];

        DB::transaction(function () use ($event, $sender, &$created, &$skipped) {
            $invitedTalents = $event->invited_talents ?? [];
            $invitedOrganisers = $event->invited_organisers ?? [];
            $invitedVenues = $event->invited_venues ?? [];

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
            return null;
        }

        $existing = EventInvitation::where('event_id', $event->id)
            ->where('receiver_id', $receiverId)
            ->first();

        if ($existing) {
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
     * Send invitation email (queued to 'emails' queue).
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
            Mail::to($receiver->email)->queue(new EventInvitationMail($invitation));

            EventInvitationLog::log($invitation, EventInvitationLog::ACTION_EMAIL_SENT, $invitation->sender_id);

            Log::info('Invitation email queued', [
                'invitation_id' => $invitation->id,
                'receiver_email' => $receiver->email,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to queue invitation email', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
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
     * Get invitations for a user.
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
}
