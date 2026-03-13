<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\EventInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ChatController extends Controller
{
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
     * Requires: authenticated, premium account.
     */
    public function validateMessage(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isPremiumAccount()) {
            return response()->json([
                'success' => false,
                'can_send' => false,
                'message' => 'Premium account required to use global chat.',
                'reason' => 'not_premium',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'can_send' => true,
        ]);
    }
}
