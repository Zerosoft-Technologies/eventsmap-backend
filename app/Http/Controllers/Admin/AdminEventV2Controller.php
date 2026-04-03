<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\EventResource;
use App\Models\EventV2;
use App\Services\V2\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AdminEventV2Controller - Admin moderation for V2 Events.
 *
 * Provides endpoints for event moderation, approval, suspension,
 * and trash management. Requires admin authentication.
 */
class AdminEventV2Controller extends Controller
{
    public function __construct(
        private readonly EventService $eventService
    ) {}

    /**
     * GET /api/admin/events
     *
     * List all events with admin filters (including pending approval).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:draft,upcoming,live,completed,cancelled,suspended',
            'is_approved' => 'nullable|boolean',
            'user_id' => 'nullable|integer|exists:users,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'sort' => 'nullable|string|in:event_date,created_at,title,view_count',
            'order' => 'nullable|string|in:asc,desc',
        ]);

        $query = EventV2::query()
            ->with(['category', 'venue', 'user']);

        // Search filter
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->input('search');
            $q->where(function ($query) use ($search) {
                $query->where('title', 'ILIKE', "%{$search}%")
                    ->orWhere('address', 'ILIKE', "%{$search}%")
                    ->orWhere('slug', 'ILIKE', "%{$search}%");
            });
        });

        // Status filter
        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('status', $request->input('status'));
        });

        // Approval filter
        $query->when($request->has('is_approved'), function ($q) use ($request) {
            $q->where('is_approved', $request->boolean('is_approved'));
        });

        // User filter
        $query->when($request->filled('user_id'), function ($q) use ($request) {
            $q->where('user_id', $request->input('user_id'));
        });

        // Category filter
        $query->when($request->filled('category_id'), function ($q) use ($request) {
            $q->where('category_id', $request->input('category_id'));
        });

        // Date range filter
        $query->when($request->filled('date_from'), function ($q) use ($request) {
            $q->where('event_date', '>=', $request->input('date_from'));
        });
        $query->when($request->filled('date_to'), function ($q) use ($request) {
            $q->where('event_date', '<=', $request->input('date_to'));
        });

        // Sorting
        $sort = $request->input('sort', 'created_at');
        $order = $request->input('order', 'desc');
        $query->orderBy($sort, $order);

        // Pagination
        $perPage = $request->input('per_page', 20);
        $events = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Events fetched successfully',
            'data' => [
                'events' => EventResource::collection($events->items()),
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/admin/events/trashed
     *
     * List soft-deleted events.
     */
    public function trashed(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $request->input('per_page', 20);
        $events = EventV2::onlyTrashed()
            ->with(['category', 'user'])
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Trashed events fetched successfully',
            'data' => [
                'events' => EventResource::collection($events->items()),
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                ],
            ],
        ]);
    }

    /**
     * PATCH /api/admin/events/{id}/restore
     *
     * Restore a soft-deleted event.
     */
    public function restore(int $id): JsonResponse
    {
        $event = EventV2::onlyTrashed()->findOrFail($id);

        $event = $this->eventService->restore($event);

        return response()->json([
            'success' => true,
            'message' => 'Event restored successfully',
            'data' => new EventResource($event),
        ]);
    }

    /**
     * DELETE /api/admin/events/{id}/force
     *
     * Permanently delete an event.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $event = EventV2::withTrashed()->findOrFail($id);

        $this->eventService->forceDelete($event);

        return response()->json([
            'success' => true,
            'message' => 'Event permanently deleted',
        ]);
    }

    /**
     * PATCH /api/admin/events/{id}/status
     *
     * Update event status (admin moderation actions).
     * Actions: approve, suspend, cancel, complete, unsuspend
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|string|in:approve,suspend,cancel,complete,unsuspend',
            'reason' => 'nullable|string|max:1000',
        ]);

        $event = EventV2::findOrFail($id);
        $action = $request->input('action');
        $admin = $request->user();

        $event = match ($action) {
            'approve' => $this->eventService->approve($event, $admin),
            'suspend' => $this->eventService->suspend($event, $admin, $request->input('reason')),
            'cancel' => $this->eventService->cancel($event, $admin),
            'complete' => $this->eventService->markCompleted($event, $admin),
            'unsuspend' => $this->eventService->unsuspend($event, $admin),
        };

        return response()->json([
            'success' => true,
            'message' => "Event {$action}d successfully",
            'data' => new EventResource($event),
        ]);
    }

    /**
     * GET /api/admin/events/pending
     *
     * List events pending approval.
     */
    public function pending(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $request->input('per_page', 20);
        $events = EventV2::where('is_approved', false)
            ->whereNotIn('status', [EventV2::STATUS_CANCELLED, EventV2::STATUS_SUSPENDED])
            ->with(['category', 'user'])
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Pending events fetched successfully',
            'data' => [
                'events' => EventResource::collection($events->items()),
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/admin/events/stats
     *
     * Get event statistics for admin dashboard.
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total' => EventV2::count(),
            'pending_approval' => EventV2::where('is_approved', false)
                ->whereNotIn('status', [EventV2::STATUS_CANCELLED, EventV2::STATUS_SUSPENDED])
                ->count(),
            'approved' => EventV2::where('is_approved', true)->count(),
            'suspended' => EventV2::where('status', EventV2::STATUS_SUSPENDED)->count(),
            'cancelled' => EventV2::where('status', EventV2::STATUS_CANCELLED)->count(),
            'trashed' => EventV2::onlyTrashed()->count(),
            'by_status' => [
                'draft' => EventV2::where('status', EventV2::STATUS_DRAFT)->count(),
                'upcoming' => EventV2::where('status', EventV2::STATUS_UPCOMING)->count(),
                'live' => EventV2::where('status', EventV2::STATUS_LIVE)->count(),
                'completed' => EventV2::where('status', EventV2::STATUS_COMPLETED)->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Event stats fetched successfully',
            'data' => $stats,
        ]);
    }
}
