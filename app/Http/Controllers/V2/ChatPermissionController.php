<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\EventV2;
use App\Models\User;
use App\Services\V2\ChatPermissionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatPermissionController extends Controller
{
    public function __construct(
        private readonly ChatPermissionService $chatService
    ) {}

    /**
     * GET /api/v2/events/{event_id}/chat-users
     *
     * Get users who can participate in the event chat.
     */
    public function chatUsers(Request $request, int $eventId): JsonResponse
    {
        $event = EventV2::findOrFail($eventId);

        $accessCheck = $this->chatService->canAccessChat($event, $request->user());
        if (!$accessCheck['can_chat']) {
            return response()->json([
                'success' => false,
                'message' => $accessCheck['message'],
            ], 403);
        }

        $users = $this->chatService->getChatUsers($event);

        return response()->json([
            'success' => true,
            'message' => 'Chat users fetched successfully',
            'data' => $users,
        ]);
    }

    /**
     * GET /api/v2/events/{event_id}/chat-access
     *
     * Check if the authenticated user can access the event chat.
     */
    public function chatAccess(Request $request, int $eventId): JsonResponse
    {
        $event = EventV2::findOrFail($eventId);

        $accessCheck = $this->chatService->canAccessChat($event, $request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'can_chat' => $accessCheck['can_chat'],
                'message' => $accessCheck['message'],
            ],
        ]);
    }

    /**
     * POST /api/v2/events/{event_id}/chat/validate-message
     *
     * Validate if user can send a message (rate limiting + permissions).
     * Call this before sending a message to Firebase.
     */
    public function validateMessage(Request $request, int $eventId): JsonResponse
    {
        $event = EventV2::findOrFail($eventId);

        $result = $this->chatService->canSendMessage($event, $request->user());

        if (!$result['can_chat']) {
            return response()->json([
                'success' => false,
                'can_send' => false,
                'message' => $result['message'],
                'reason' => $result['reason'],
            ], 403);
        }

        return response()->json([
            'success' => true,
            'can_send' => true,
            'message' => null,
        ]);
    }

    /**
     * POST /api/v2/events/{event_id}/chat/report
     *
     * Report a message/user in the chat.
     */
    public function reportMessage(Request $request, int $eventId): JsonResponse
    {
        $request->validate([
            'reported_user_id' => 'required|integer|exists:users,id',
            'message_id' => 'nullable|string|max:255',
            'reason' => 'required|string|max:1000',
        ]);

        $event = EventV2::findOrFail($eventId);

        $accessCheck = $this->chatService->canAccessChat($event, $request->user());
        if (!$accessCheck['can_chat']) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot report messages in a chat you cannot access.',
            ], 403);
        }

        $report = $this->chatService->reportMessage(
            $event,
            $request->user(),
            $request->input('reported_user_id'),
            $request->input('reason'),
            $request->input('message_id')
        );

        return response()->json([
            'success' => true,
            'message' => 'Report submitted successfully',
            'data' => [
                'report_id' => $report->id,
            ],
        ]);
    }

    /**
     * POST /api/v2/events/{event_id}/chat/mute
     *
     * Mute a user in the event chat (event owner only).
     */
    public function muteUser(Request $request, int $eventId): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'reason' => 'nullable|string|max:500',
            'duration_minutes' => 'nullable|integer|min:1|max:43200',
        ]);

        $event = EventV2::findOrFail($eventId);

        if ($event->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only the event owner can mute users.',
            ], 403);
        }

        $userId = $request->input('user_id');

        if ($userId === $event->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot mute the event owner.',
            ], 400);
        }

        $expiresAt = $request->has('duration_minutes')
            ? Carbon::now()->addMinutes($request->input('duration_minutes'))
            : null;

        $this->chatService->muteUser(
            $event,
            $userId,
            $request->user(),
            $request->input('reason'),
            $expiresAt
        );

        return response()->json([
            'success' => true,
            'message' => 'User muted successfully',
        ]);
    }

    /**
     * POST /api/v2/events/{event_id}/chat/unmute
     *
     * Unmute a user in the event chat (event owner only).
     */
    public function unmuteUser(Request $request, int $eventId): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $event = EventV2::findOrFail($eventId);

        if ($event->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only the event owner can unmute users.',
            ], 403);
        }

        $this->chatService->unmuteUser(
            $event,
            $request->input('user_id'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'User unmuted successfully',
        ]);
    }

    /**
     * POST /api/v2/events/{event_id}/chat/ban
     *
     * Ban a user from the event chat (event owner only).
     */
    public function banUser(Request $request, int $eventId): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $event = EventV2::findOrFail($eventId);

        if ($event->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only the event owner can ban users.',
            ], 403);
        }

        $userId = $request->input('user_id');

        if ($userId === $event->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot ban the event owner.',
            ], 400);
        }

        $this->chatService->banUser(
            $event,
            $userId,
            $request->user(),
            $request->input('reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'User banned from chat successfully',
        ]);
    }

    /**
     * POST /api/v2/events/{event_id}/chat/unban
     *
     * Unban a user from the event chat (event owner only).
     */
    public function unbanUser(Request $request, int $eventId): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $event = EventV2::findOrFail($eventId);

        if ($event->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only the event owner can unban users.',
            ], 403);
        }

        $this->chatService->unbanUser(
            $event,
            $request->input('user_id'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'User unbanned from chat successfully',
        ]);
    }
}
