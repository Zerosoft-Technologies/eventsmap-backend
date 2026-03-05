<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\EventV2;
use App\Services\V2\ChatPermissionService;
use App\Services\V2\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FirebaseTokenController extends Controller
{
    public function __construct(
        private readonly FirebaseService $firebaseService,
        private readonly ChatPermissionService $chatService
    ) {}

    /**
     * POST /api/v2/firebase/token
     *
     * Generate a Firebase custom token for the authenticated user.
     */
    public function generateToken(Request $request): JsonResponse
    {
        if (!$this->firebaseService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Firebase is not configured on this server.',
            ], 503);
        }

        $token = $this->firebaseService->generateCustomToken($request->user());

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Firebase token.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Firebase token generated successfully',
            'data' => [
                'token' => $token,
                'expires_in' => 3600,
            ],
        ]);
    }

    /**
     * POST /api/v2/firebase/token/event/{event_id}
     *
     * Generate a Firebase custom token for event chat access.
     * Includes event-specific permissions in the token claims.
     */
    public function generateEventToken(Request $request, int $eventId): JsonResponse
    {
        if (!$this->firebaseService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Firebase is not configured on this server.',
            ], 503);
        }

        $event = EventV2::findOrFail($eventId);

        $accessCheck = $this->chatService->canAccessChat($event, $request->user());

        $permissions = [
            'can_chat' => $accessCheck['can_chat'],
            'is_owner' => $event->user_id === $request->user()->id,
            'is_muted' => $request->user()->isMutedInEventChat($eventId),
        ];

        $token = $this->firebaseService->generateEventChatToken(
            $request->user(),
            $eventId,
            $permissions
        );

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Firebase token.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Firebase event token generated successfully',
            'data' => [
                'token' => $token,
                'expires_in' => 3600,
                'permissions' => $permissions,
            ],
        ]);
    }
}
