<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\EventInvitationProfileResource;
use App\Models\EventV2;
use App\Services\V2\EventInvitationProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Profiles available to invite when creating or editing premium events.
 */
class EventInvitationProfileController extends Controller
{
    public function __construct(
        private readonly EventInvitationProfileService $profileService,
    ) {}

    /**
     * GET /api/v2/invitation-profiles
     *
     * Published talent, organiser, and venue profiles for the invitation picker.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'profile_type' => 'nullable',
            'search' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'account_type' => 'nullable|string|in:free,premium',
            'exclude_self' => 'nullable|boolean',
            'exclude_invited' => 'nullable|boolean',
        ]);

        $rows = $this->profileService->listForPicker($request);

        return response()->json([
            'success' => true,
            'message' => 'Invitation profiles fetched successfully',
            'data' => EventInvitationProfileResource::collection($rows),
        ]);
    }

    /**
     * GET /api/v2/events/{eventId}/invitation-profiles
     *
     * Same picker list. Use {@code exclude_invited=1} to omit users already on the event;
     * {@code exclude_self=1} to hide profiles owned by the current user (default off).
     */
    public function forEvent(Request $request, int $eventId): JsonResponse
    {
        $request->validate([
            'profile_type' => 'nullable',
            'search' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'account_type' => 'nullable|string|in:free,premium',
            'exclude_self' => 'nullable|boolean',
            'exclude_invited' => 'nullable|boolean',
        ]);

        $event = EventV2::findOrFail($eventId);

        $user = $request->user();
        if (! $user || ($event->user_id !== $user->id && ! $user->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to manage invitations for this event.',
            ], 403);
        }

        $rows = $this->profileService->listForPicker($request, $event);

        return response()->json([
            'success' => true,
            'message' => 'Invitation profiles fetched successfully',
            'data' => EventInvitationProfileResource::collection($rows),
        ]);
    }
}
