<?php

namespace App\Http\Controllers\V2;

use App\Exceptions\GuestInvitationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\StoreGuestInvitationRequest;
use App\Http\Requests\V2\ValidateGuestInvitationEmailRequest;
use App\Models\EventV2;
use App\Services\V2\GuestInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventGuestInvitationController extends Controller
{
    public function __construct(
        private readonly GuestInvitationService $guestInvitationService,
    ) {}

    /**
     * POST /api/v2/events/{event}/guest-invitations/validate-email
     */
    public function validateEmail(ValidateGuestInvitationEmailRequest $request, int $event): JsonResponse
    {
        $eventModel = EventV2::findOrFail($event);
        $this->authorizeEventOwner($request, $eventModel);

        $validated = $request->validated();
        $result = $this->guestInvitationService->validateEmailForEvent(
            $eventModel,
            $validated['email'],
            $validated['receiver_type'],
        );

        if ($result['code'] === 'OK') {
            return response()->json([
                'success' => true,
                'code' => 'OK',
                'message' => $result['message'],
            ]);
        }

        return response()->json([
            'success' => false,
            'code' => $result['code'],
            'message' => $result['message'],
        ], 422);
    }

    /**
     * POST /api/v2/events/{event}/guest-invitations
     */
    public function store(StoreGuestInvitationRequest $request, int $event): JsonResponse
    {
        $eventModel = EventV2::findOrFail($event);
        $this->authorizeEventOwner($request, $eventModel);

        $validated = $request->validated();

        try {
            $invitation = $this->guestInvitationService->inviteByEmail(
                $eventModel,
                $request->user(),
                $validated['email'],
                $validated['receiver_type'],
                $validated['name'] ?? null,
            );
        } catch (GuestInvitationException $e) {
            return response()->json([
                'success' => false,
                'code' => $e->errorCode,
                'message' => $e->getMessage(),
            ], $e->httpStatus);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent successfully.',
            'data' => [
                'id' => $invitation->id,
                'email' => $invitation->invitee_email,
                'receiver_type' => $invitation->receiver_type,
                'status' => $invitation->status,
            ],
        ], 201);
    }

    /**
     * GET /api/v2/guest-invitations/token/{token}
     */
    public function showByToken(string $token): JsonResponse
    {
        $result = $this->guestInvitationService->resolveTokenForRegistration($token);

        if (! ($result['valid'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Invalid invitation.',
            ], 410);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    private function authorizeEventOwner(Request $request, EventV2 $event): void
    {
        $user = $request->user();
        if ($event->user_id !== $user->id && ! $user->isAdmin()) {
            abort(403, 'You are not authorized to manage invitations for this event.');
        }
    }
}
