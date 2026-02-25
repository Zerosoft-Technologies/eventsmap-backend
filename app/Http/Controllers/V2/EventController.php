<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\V2\StoreEventRequest;
use App\Http\Requests\V2\UpdateEventRequest;
use App\Http\Resources\V2\EventResource;
use App\Http\Resources\V2\EventSidebarResource;
use App\Models\EventV2;
use App\Services\V2\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * EventController - V2 Event CRUD for authenticated users.
 *
 * Provides event listing, creation, viewing, updating, and deletion
 * with proper ownership enforcement.
 */
class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService
    ) {}

    /**
     * GET /api/v2/events
     *
     * List events with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'status' => 'nullable|string|in:draft,upcoming,live,completed,cancelled',
            'entrance_status' => 'nullable|string|in:free,paid,sold_out,cancelled',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'sort' => 'nullable|string|in:event_date,created_at,title',
            'order' => 'nullable|string|in:asc,desc',
        ]);

        $query = EventV2::query()
            ->with(['category', 'subcategories', 'venue', 'organisers', 'talents']);

        // Search filter
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->input('search');
            $q->where(function ($query) use ($search) {
                $query->where('title', 'ILIKE', "%{$search}%")
                    ->orWhere('address', 'ILIKE', "%{$search}%");
            });
        });

        // Category filter
        $query->when($request->filled('category_id'), function ($q) use ($request) {
            $q->where('category_id', $request->input('category_id'));
        });

        // Status filter
        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('status', $request->input('status'));
        });

        // Entrance status filter
        $query->when($request->filled('entrance_status'), function ($q) use ($request) {
            $q->where('entrance_status', $request->input('entrance_status'));
        });

        // Date range filter
        $query->when($request->filled('date_from'), function ($q) use ($request) {
            $q->where('event_date', '>=', $request->input('date_from'));
        });
        $query->when($request->filled('date_to'), function ($q) use ($request) {
            $q->where('event_date', '<=', $request->input('date_to'));
        });

        // Sorting
        $sort = $request->input('sort', 'event_date');
        $order = $request->input('order', 'asc');
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
     * GET /api/v2/my-events
     *
     * List events created by the authenticated user (sidebar).
     * Returns minimal fields for efficient sidebar rendering.
     */
    public function myEvents(Request $request): JsonResponse
    {
        $events = $this->eventService->getUserEvents($request->user());

        return response()->json([
            'success' => true,
            'message' => 'My events fetched',
            'data' => EventSidebarResource::collection($events),
        ]);
    }

    /**
     * POST /api/v2/events
     *
     * Create a new event.
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = $this->eventService->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully',
            'data' => new EventResource($event),
        ], 201);
    }

    /**
     * GET /api/v2/events/{id}
     *
     * Show a single event for editing.
     * Only the event owner or an admin can access.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $event = EventV2::with(['category', 'subcategories', 'venue', 'organisers', 'talents', 'user'])
            ->findOrFail($id);

        // Enforce ownership: only owner or admin can view for editing
        $user = $request->user();
        if (!$event->isOwner($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this event.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Event fetched successfully',
            'data' => new EventResource($event),
        ]);
    }

    /**
     * PUT /api/v2/events/{id}
     *
     * Update an event. Only owner or admin can update.
     */
    public function update(UpdateEventRequest $request, int $id): JsonResponse
    {
        $event = EventV2::findOrFail($id);

        $this->authorize('update', $event);

        $event = $this->eventService->update($event, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully',
            'data' => new EventResource($event),
        ]);
    }

    /**
     * DELETE /api/v2/events/{id}
     *
     * Delete an event (soft delete). Only owner or admin can delete.
     */
    public function destroy(int $id): JsonResponse
    {
        $event = EventV2::findOrFail($id);

        $this->authorize('delete', $event);

        $this->eventService->delete($event);

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully',
        ]);
    }
}
