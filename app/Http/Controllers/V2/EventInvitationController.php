<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\V2\IndexInvitedEventsRequest;
use App\Http\Requests\V2\RespondToInvitationRequest;
use App\Http\Resources\V2\ReceivedInvitationResource;
use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Services\V2\EventInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventInvitationController extends Controller
{
    public function __construct(
        private readonly EventInvitationService $invitationService
    ) {}

    /**
     * GET /api/v2/invitations
     * GET /api/v2/invited-events
     *
     * Paginated invitations received by the authenticated user with full {@see EventV2} payloads.
     */
    public function index(IndexInvitedEventsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $paginator = $this->invitationService->paginateReceivedInvitations($request->user(), [
            'status' => $validated['status'] ?? null,
            'event_timing' => $validated['event_timing'] ?? null,
            'per_page' => $validated['per_page'] ?? null,
            'page' => $validated['page'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invitations fetched successfully',
            'data' => [
                'invitations' => ReceivedInvitationResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/v2/events/{event_id}/invitations
     *
     * Get all invitations for a specific event (owner only).
     */
    public function eventInvitations(Request $request, int $eventId): JsonResponse
    {
        $event = EventV2::findOrFail($eventId);

        if ($event->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view invitations for this event.',
            ], 403);
        }

        $invitations = $this->invitationService->getEventInvitations($event);

        return response()->json([
            'success' => true,
            'message' => 'Event invitations fetched successfully',
            'data' => $invitations->map(fn ($invitation) => [
                'id' => $invitation->id,
                'receiver' => $invitation->receiver ? [
                    'id' => $invitation->receiver->id,
                    'name' => $invitation->receiver->name,
                    'email' => $invitation->receiver->email,
                    'profile_type' => $invitation->receiver->profile_type,
                ] : null,
                'invitee_email' => $invitation->invitee_email,
                'invitee_name' => $invitation->invitee_name,
                'is_guest' => $invitation->isGuestInvitation(),
                'receiver_type' => $invitation->receiver_type,
                'status' => $invitation->status,
                'created_at' => $invitation->created_at->toIso8601String(),
                'responded_at' => $invitation->responded_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * POST /api/v2/event-invitations/{id}/respond
     *
     * Respond to an invitation (accept or reject).
     * Supports:
     * - Authenticated: user must be the receiver
     * - Token-based: provide token from email link (no auth required)
     */
    public function respond(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:accepted,rejected',
            'token' => 'nullable|string|size:64',
        ]);

        $invitation = EventInvitation::with(['event', 'sender', 'receiver'])->findOrFail($id);

        try {
            // Try to resolve authenticated user even on this public route
            $user = auth('sanctum')->user();

            $invitation = $this->invitationService->respond(
                $invitation,
                $request->input('status'),
                $user,
                $request->input('token')
            );

            return response()->json([
                'success' => true,
                'message' => 'Invitation ' . $request->input('status') . ' successfully',
                'data' => [
                    'id' => $invitation->id,
                    'status' => $invitation->status,
                    'responded_at' => $invitation->responded_at->toIso8601String(),
                    'event' => [
                        'id' => $invitation->event->id,
                        'title' => $invitation->event->title,
                    ],
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 408);
        }
    }

    /**
     * POST /api/v2/invitations/{id}/respond
     *
     * Authenticated-only response to an invitation (same rules as token flow; no token required).
     */
    public function respondAuthenticated(RespondToInvitationRequest $request, int $id): JsonResponse
    {
        $invitation = EventInvitation::with(['event', 'sender', 'receiver'])->findOrFail($id);

        try {
            $invitation = $this->invitationService->respond(
                $invitation,
                $request->validated('status'),
                $request->user(),
                null
            );

            $invitation = $this->invitationService->decorateInvitationForDetailResponse($invitation);

            return response()->json([
                'success' => true,
                'message' => 'Invitation '.$request->validated('status').' successfully',
                'data' => new ReceivedInvitationResource($invitation),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * DELETE /api/v2/event-invitations/{id}
     *
     * Cancel an invitation (sender or event owner only).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $invitation = EventInvitation::with(['event'])->findOrFail($id);

        try {
            $this->invitationService->cancelInvitation($invitation, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Invitation cancelled successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * POST /api/v2/event-invitations/{id}/resend
     *
     * Resend invitation email.
     */
    public function resend(Request $request, int $id): JsonResponse
    {
        $invitation = EventInvitation::with(['event', 'receiver'])->findOrFail($id);

        try {
            $this->invitationService->resendInvitation($invitation, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Invitation email resent successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/v2/events/{event_id}/invitations/send
     *
     * Manually trigger sending invitations for an event.
     */
    public function sendInvitations(Request $request, int $eventId): JsonResponse
    {
        $event = EventV2::findOrFail($eventId);

        if ($event->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to send invitations for this event.',
            ], 403);
        }

        $result = $this->invitationService->createInvitationsForEvent($event, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Invitations processed',
            'data' => [
                'created_count' => count($result['created']),
                'skipped_count' => count($result['skipped']),
            ],
        ]);
    }
}
