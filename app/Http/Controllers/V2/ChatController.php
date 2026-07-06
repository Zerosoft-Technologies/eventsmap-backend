<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\EventInvitation;
use App\Models\User;
use App\Services\V2\ChatBlockService;
use App\Services\V2\FirebaseNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatBlockService $chatBlockService,
        private readonly FirebaseNotificationService $firebaseNotificationService,
    ) {}

    /**
     * GET /api/v2/chat/users
     *
     * Return premium users who were invited by the logged-in user and accepted.
     * Excludes the logged-in user.
     */
    public function getChatUsers(Request $request): JsonResponse
    {
        $user = $request->user();

        $userColumns = ['users.id', 'users.name', 'users.account_type'];
        if (Schema::hasColumn('users', 'profile_image')) {
            $userColumns[] = 'users.profile_image';
        }

        $users = User::query()
            ->select($userColumns)
            ->join('event_invitations', 'event_invitations.receiver_id', '=', 'users.id')
            ->where('event_invitations.sender_id', $user->id)
            ->where('event_invitations.status', EventInvitation::STATUS_ACCEPTED)
            ->where('users.account_type', User::ACCOUNT_PREMIUM)
            ->where('users.id', '!=', $user->id)
            ->distinct()
            ->orderBy('users.name')
            ->get()
            ->map(function (User $u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'profile_image' => Schema::hasColumn('users', 'profile_image') ? $u->profile_image : null,
                    'account_type' => $u->account_type,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Chat users fetched successfully',
            'data' => $users,
        ]);
    }

    /**
     * POST /api/v2/chat/validate-message
     *
     * Validate if user can send a message in global chat.
     * Requires: authenticated registered user.
     */
    public function validateMessage(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'can_send' => false,
                'message' => 'Authentication required to use chat.',
                'reason' => 'not_authenticated',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'can_send' => true,
        ]);
    }

    /**
     * POST /api/v2/chat/notify-message
     *
     * Record a delivered chat message in Firestore for the receiver (in-app notification trail).
     */
    public function notifyMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'receiver_id' => ['required', 'integer', 'min:1'],
            'conversation_id' => ['required', 'string', 'max:128'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        if ((int) $validated['receiver_id'] === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid receiver.',
            ], 422);
        }

        if ($this->chatBlockService->isMessagingBlocked($user->id, (int) $validated['receiver_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Messaging is blocked.',
            ], 403);
        }

        $this->firebaseNotificationService->createChatMessageNotification(
            receiverId: (int) $validated['receiver_id'],
            senderId: $user->id,
            senderName: $user->name ?? 'Someone',
            conversationId: $validated['conversation_id'],
            messagePreview: Str::limit($validated['message'], 120),
        );

        return response()->json([
            'success' => true,
            'message' => 'Chat notification recorded.',
        ]);
    }

    /**
     * GET /api/v2/chat/blocks
     */
    public function listBlocks(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->chatBlockService->listForUser($request->user()),
        ]);
    }

    /**
     * POST /api/v2/chat/block/{user_id}
     */
    public function blockUser(Request $request, int $user_id): JsonResponse
    {
        try {
            $this->chatBlockService->block($request->user(), $user_id);
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile blocked successfully.',
        ]);
    }

    /**
     * DELETE /api/v2/chat/block/{user_id}
     */
    public function unblockUser(Request $request, int $user_id): JsonResponse
    {
        $this->chatBlockService->unblock($request->user(), $user_id);

        return response()->json([
            'success' => true,
            'message' => 'Profile unblocked successfully.',
        ]);
    }

    /**
     * GET /api/v2/chat/can-message/{user_id}
     */
    public function canMessage(Request $request, int $user_id): JsonResponse
    {
        $user = $request->user();

        if ($user_id === $user->id) {
            return response()->json([
                'success' => true,
                'can_message' => false,
                'reason' => 'self',
            ]);
        }

        if ($this->chatBlockService->isMessagingBlocked($user->id, $user_id)) {
            return response()->json([
                'success' => true,
                'can_message' => false,
                'reason' => 'blocked',
            ]);
        }

        return response()->json([
            'success' => true,
            'can_message' => true,
        ]);
    }
}
