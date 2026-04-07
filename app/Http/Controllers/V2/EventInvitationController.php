<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
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
     *
     * Get all invitations received by the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string|in:pending,accepted,rejected',
        ]);

        $invitations = $this->invitationService->getUserInvitations(
            $request->user(),
            $request->input('status')
        );

        return response()->json([
            'success' => true,
            'message' => 'Invitations fetched successfully',
            'data' => $invitations->map(fn($invitation) => [
                'id' => $invitation->id,
                'event' => [
                    'id' => $invitation->event->id,
                    'title' => $invitation->event->title,
                    'event_date' => $invitation->event->event_date->format('Y-m-d'),
                    'start_time' => $invitation->event->start_time,
                    'end_time' => $invitation->event->end_time,
                    'address' => $invitation->event->address,
                    'image_path' => $invitation->event->image_path,
                ],
                'sender' => [
                    'id' => $invitation->sender->id,
                    'name' => $invitation->sender->name,
                ],
                'receiver_type' => $invitation->receiver_type,
                'status' => $invitation->status,
                'created_at' => $invitation->created_at->toIso8601String(),
                'responded_at' => $invitation->responded_at?->toIso8601String(),
            ]),
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
            'data' => $invitations->map(fn($invitation) => [
                'id' => $invitation->id,
                'receiver' => [
                    'id' => $invitation->receiver->id,
                    'name' => $invitation->receiver->name,
                    'email' => $invitation->receiver->email,
                    'profile_type' => $invitation->receiver->profile_type,
                ],
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
