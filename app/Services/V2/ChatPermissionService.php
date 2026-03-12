<?php

namespace App\Services\V2;

use App\Models\ChatBan;
use App\Models\ChatModerationLog;
use App\Models\ChatReport;
use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatPermissionService
{
    /**
     * Check if a user can access the chat for an event.
     */
    public function canAccessChat(EventV2 $event, User $user): array
    {
        if ($event->user_id === $user->id) {
            return [
                'can_chat' => true,
                'message' => null,
                'reason' => 'event_owner',
            ];
        }

        $hasAcceptedInvitation = EventInvitation::where('event_id', $event->id)
            ->where('receiver_id', $user->id)
            ->where('status', EventInvitation::STATUS_ACCEPTED)
            ->exists();

        if ($hasAcceptedInvitation) {
            if ($user->isBannedFromEventChat($event->id)) {
                return [
                    'can_chat' => false,
                    'message' => 'You have been banned from this event chat.',
                    'reason' => 'banned',
                ];
            }

            return [
                'can_chat' => true,
                'message' => null,
                'reason' => 'accepted_invitation',
            ];
        }

        $hasPendingInvitation = EventInvitation::where('event_id', $event->id)
            ->where('receiver_id', $user->id)
            ->where('status', EventInvitation::STATUS_PENDING)
            ->exists();

        if ($hasPendingInvitation) {
            return [
                'can_chat' => false,
                'message' => 'You have a pending invitation. Please accept it to join the chat.',
                'reason' => 'pending_invitation',
            ];
        }

        $hasInvitedUsers = $this->eventHasInvitedUsers($event);

        if (!$hasInvitedUsers) {
            return [
                'can_chat' => false,
                'message' => "You don't have invited users yet, so chat is not available.",
                'reason' => 'no_invitations',
            ];
        }

        return [
            'can_chat' => false,
            'message' => 'You are not authorized to participate in this event chat.',
            'reason' => 'not_invited',
        ];
    }

    /**
     * Get all users who can participate in the event chat.
     */
    public function getChatUsers(EventV2 $event): Collection
    {
        $userIds = collect();

        $userIds->push($event->user_id);

        $acceptedInvitations = EventInvitation::where('event_id', $event->id)
            ->where('status', EventInvitation::STATUS_ACCEPTED)
            ->pluck('receiver_id');

        $userIds = $userIds->merge($acceptedInvitations)->unique();

        $bannedUserIds = ChatBan::where('event_id', $event->id)
            ->where('type', ChatBan::TYPE_BAN)
            ->active()
            ->pluck('user_id');

        $userIds = $userIds->diff($bannedUserIds);

        return User::whereIn('id', $userIds)
            ->select(['id', 'name', 'email', 'profile_type'])
            ->get()
            ->map(function ($user) use ($event) {
                $invitation = EventInvitation::where('event_id', $event->id)
                    ->where('receiver_id', $user->id)
                    ->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'profile_image' => null,
                    'account_type' => $invitation?->receiver_type ?? ($event->user_id === $user->id ? 'owner' : 'unknown'),
                    'is_owner' => $event->user_id === $user->id,
                    'is_muted' => $user->isMutedInEventChat($event->id),
                ];
            });
    }

    /**
     * Validate if a user can send a message (rate limiting + permissions).
     */
    public function canSendMessage(EventV2 $event, User $user): array
    {
        $accessCheck = $this->canAccessChat($event, $user);
        if (!$accessCheck['can_chat']) {
            return $accessCheck;
        }

        if ($user->isMutedInEventChat($event->id)) {
            $mute = ChatBan::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->where('type', ChatBan::TYPE_MUTE)
                ->active()
                ->first();

            $expiresMessage = $mute && $mute->expires_at
                ? ' Expires at ' . $mute->expires_at->format('Y-m-d H:i')
                : '';

            return [
                'can_chat' => false,
                'message' => 'You are currently muted in this chat.' . $expiresMessage,
                'reason' => 'muted',
            ];
        }

        return [
            'can_chat' => true,
            'message' => null,
            'reason' => 'allowed',
        ];
    }

    /**
     * Report a user/message in the chat.
     */
    public function reportMessage(
        EventV2 $event,
        User $reporter,
        int $reportedUserId,
        string $reason,
        ?string $messageId = null
    ): ChatReport {
        $report = ChatReport::create([
            'event_id' => $event->id,
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reportedUserId,
            'reported_message_id' => $messageId,
            'reason' => $reason,
            'status' => ChatReport::STATUS_PENDING,
        ]);

        Log::info('Chat message reported', [
            'report_id' => $report->id,
            'event_id' => $event->id,
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reportedUserId,
            'message_id' => $messageId,
        ]);

        return $report;
    }

    /**
     * Mute a user in an event chat.
     */
    public function muteUser(
        EventV2 $event,
        int $userId,
        User $performer,
        ?string $reason = null,
        ?Carbon $expiresAt = null
    ): ChatBan {
        ChatBan::where('event_id', $event->id)
            ->where('user_id', $userId)
            ->where('type', ChatBan::TYPE_MUTE)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $mute = ChatBan::create([
            'user_id' => $userId,
            'event_id' => $event->id,
            'type' => ChatBan::TYPE_MUTE,
            'reason' => $reason,
            'banned_by' => $performer->id,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);

        ChatModerationLog::create([
            'action' => ChatModerationLog::ACTION_MUTE,
            'user_id' => $userId,
            'event_id' => $event->id,
            'reason' => $reason,
            'performed_by' => $performer->id,
            'expires_at' => $expiresAt,
        ]);

        Log::info('User muted in chat', [
            'user_id' => $userId,
            'event_id' => $event->id,
            'performed_by' => $performer->id,
            'expires_at' => $expiresAt?->toIso8601String(),
        ]);

        return $mute;
    }

    /**
     * Unmute a user in an event chat.
     */
    public function unmuteUser(EventV2 $event, int $userId, User $performer): void
    {
        ChatBan::where('event_id', $event->id)
            ->where('user_id', $userId)
            ->where('type', ChatBan::TYPE_MUTE)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        ChatModerationLog::create([
            'action' => ChatModerationLog::ACTION_UNMUTE,
            'user_id' => $userId,
            'event_id' => $event->id,
            'performed_by' => $performer->id,
        ]);

        Log::info('User unmuted in chat', [
            'user_id' => $userId,
            'event_id' => $event->id,
            'performed_by' => $performer->id,
        ]);
    }

    /**
     * Ban a user from an event chat.
     */
    public function banUser(
        EventV2 $event,
        int $userId,
        User $performer,
        ?string $reason = null
    ): ChatBan {
        ChatBan::where('event_id', $event->id)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $ban = ChatBan::create([
            'user_id' => $userId,
            'event_id' => $event->id,
            'type' => ChatBan::TYPE_BAN,
            'reason' => $reason,
            'banned_by' => $performer->id,
            'is_active' => true,
        ]);

        ChatModerationLog::create([
            'action' => ChatModerationLog::ACTION_BAN,
            'user_id' => $userId,
            'event_id' => $event->id,
            'reason' => $reason,
            'performed_by' => $performer->id,
        ]);

        Log::warning('User banned from chat', [
            'user_id' => $userId,
            'event_id' => $event->id,
            'performed_by' => $performer->id,
            'reason' => $reason,
        ]);

        return $ban;
    }

    /**
     * Unban a user from an event chat.
     */
    public function unbanUser(EventV2 $event, int $userId, User $performer): void
    {
        ChatBan::where('event_id', $event->id)
            ->where('user_id', $userId)
            ->where('type', ChatBan::TYPE_BAN)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        ChatModerationLog::create([
            'action' => ChatModerationLog::ACTION_UNBAN,
            'user_id' => $userId,
            'event_id' => $event->id,
            'performed_by' => $performer->id,
        ]);

        Log::info('User unbanned from chat', [
            'user_id' => $userId,
            'event_id' => $event->id,
            'performed_by' => $performer->id,
        ]);
    }

    /**
     * Check if event has any invited users.
     */
    private function eventHasInvitedUsers(EventV2 $event): bool
    {
        $invitedTalents = $event->invited_talents ?? [];
        $invitedOrganisers = $event->invited_organisers ?? [];
        $invitedVenues = $event->invited_venues ?? [];

        return !empty($invitedTalents) || !empty($invitedOrganisers) || !empty($invitedVenues);
    }
}
