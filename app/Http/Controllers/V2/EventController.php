<?php

namespace App\Http\Controllers\V2;

use App\Helpers\MediaHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\StoreEventRequest;
use App\Http\Requests\V2\UpdateEventRequest;
use App\Http\Resources\V2\EventResource;
use App\Http\Resources\V2\EventSidebarResource;
use App\Models\EventV2;
use App\Services\V2\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
            // Date range (support frontend param names)
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',

            // Geo filtering (optional)
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:0.1|max:500', // km
            'radius_km' => 'nullable|numeric|min:0.1|max:500', // km
            'sort' => 'nullable|string|in:event_date,created_at,title',
            'order' => 'nullable|string|in:asc,desc',
        ]);

        $query = EventV2::query()
            ->with(['category', 'venue', 'organisers', 'talents']);

        // Search filter (ILIKE is PostgreSQL-only; MySQL uses LIKE + case-insensitive collation)
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->input('search');
            $op = $q->getConnection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'like';
            $q->where(function ($sub) use ($search, $op) {
                $sub->where('title', $op, "%{$search}%")
                    ->orWhere('address', $op, "%{$search}%");
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
        $dateFrom = $request->input('date_from') ?? $request->input('from_date');
        $dateTo = $request->input('date_to') ?? $request->input('to_date');
        if ($dateFrom) {
            $query->where('event_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('event_date', '<=', $dateTo);
        }

        // Geo radius filter (if provided)
        if ($request->filled(['lat', 'lng']) && ($request->filled('radius') || $request->filled('radius_km'))) {
            $lat = (float) $request->input('lat');
            $lng = (float) $request->input('lng');
            $radiusKm = (float) ($request->input('radius_km') ?? $request->input('radius'));

            $query->withinRadius($lat, $lng, $radiusKm)->withDistance($lat, $lng);
        }

        // Sorting
        $sort = $request->input('sort', 'event_date');
        $order = $request->input('order', 'asc');
        // If geo filter was applied, default to distance sort unless explicitly sorting by other field
        if ($request->filled(['lat', 'lng']) && ($request->filled('radius') || $request->filled('radius_km')) && !$request->filled('sort')) {
            $query->orderBy('distance_km', 'asc');
        } else {
            $query->orderBy($sort, $order);
        }

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
        Log::info('Store event request', [
            'all_data' => $request->all(),
            'has_file' => $request->hasFile('image_path'),
            'files' => $request->allFiles()
        ]);
        
        $validated = $request->validated();
        Log::info('Validated data', [
            'validated' => $validated,
            'image_path_type' => isset($validated['image_path']) ? gettype($validated['image_path']) : 'not_set'
        ]);
        
        $event = $this->eventService->create($validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully',
            'data' => new EventResource($event),
        ], 201);
    }

    /**
     * GET /api/v2/events/{id}
     *
     * Full event details for edit form. Owner or admin only.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $event = EventV2::with(['category', 'venue', 'organisers', 'talents', 'user'])->findOrFail($id);
        
        // Manually load subcategories from IDs
        $event->setRelation('subcategories', $event->subcategories_from_ids);

        $user = $request->user();
        if (!$event->isOwner($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this event.',
            ], 403);
        }

        $subcategoryIds = $event->subcategory_ids ?? $event->subcategories->pluck('id')->all();

        $data = [
            'id' => $event->id,
            'title' => $event->title,
            'event_type' => $event->event_type ?? 'free',
            'category_id' => $event->category_id,
            'subcategory_ids' => $subcategoryIds,
            'start_date' => $event->start_date?->format('Y-m-d'),
            'end_date' => $event->end_date?->format('Y-m-d'),
            'event_date' => $event->event_date?->format('Y-m-d'),
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'start_datetime' => $event->start_datetime?->toIso8601String(),
            'end_datetime' => $event->end_datetime?->toIso8601String(),
            'address' => $event->address,
            'latitude' => $event->latitude !== null ? (float) $event->latitude : null,
            'longitude' => $event->longitude !== null ? (float) $event->longitude : null,
            'dress_code' => $event->dress_code,
            'age_limit' => $event->age_limit,
            'entrance_status' => $event->entrance_status,
            'contact_phone' => $event->contact_phone,
            'contact_email' => $event->contact_email,
            'description' => $event->description ?? null,
            'contact_website' => $event->contact_website,
            'contact_box_message' => $event->contact_box_message,
            'facebook_url' => $event->facebook_url,
            'instagram_url' => $event->instagram_url,
            'tiktok_url' => $event->tiktok_url,
            'ticket_url' => $event->ticket_url,
            'booking_instructions' => $event->booking_instructions,
            'is_recurring' => (bool) ($event->is_recurring ?? false),
            'is_copy_event' => (bool) ($event->is_copy_event ?? false),
            'show_upcoming_events' => (bool) ($event->show_upcoming_events ?? false),
            'show_past_events' => (bool) ($event->show_past_events ?? false),
            'invited_talents' => is_array($event->invited_talents) ? $event->invited_talents : [],
            'invited_organisers' => is_array($event->invited_organisers) ? $event->invited_organisers : [],
            'invited_venues' => is_array($event->invited_venues) ? $event->invited_venues : [],
            'organiser_ids' => $event->organisers->pluck('id')->values()->all(),
            'talent_ids' => $event->talents->pluck('id')->values()->all(),
            'image_path' => $event->image_path,
        ];

        // Handle main image - check if it's a UUID (from gallery) or a file path
        if ($event->image_path) {
            // Check if image_path is a UUID format
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $event->image_path)) {
                // It's a UUID, fetch from gallery
                $galleryImage = \App\Models\GalleryImage::where('image_id', $event->image_path)
                    ->where('user_id', $event->user_id)
                    ->where('is_deleted', false)
                    ->first();
                
                $data['image_url'] = $galleryImage ? MediaHelper::url($galleryImage->file_path) : null;
            } else {
                // It's a regular file path
                $data['image_url'] = MediaHelper::url($event->image_path);
            }
        } else {
            $data['image_url'] = null;
        }

        // Handle additional images
        $additionalImages = $event->additional_images ?? [];
        $additionalImageUrls = [];
        
        if (!empty($additionalImages) && is_array($additionalImages)) {
            // Fetch gallery images for the additional image IDs
            $galleryImages = \App\Models\GalleryImage::whereIn('image_id', $additionalImages)
                ->where('user_id', $event->user_id)
                ->where('is_deleted', false)
                ->get()
                ->keyBy('image_id');
            
            // Build URLs maintaining the same order as the additional_images array
            foreach ($additionalImages as $imageId) {
                if (isset($galleryImages[$imageId])) {
                    $galleryImage = $galleryImages[$imageId];
                    $additionalImageUrls[] = MediaHelper::url($galleryImage->file_path);
                }
            }
        }
        
        $data['additional_images'] = $additionalImages;
        $data['additional_image_urls'] = $additionalImageUrls;

        return response()->json([
            'success' => true,
            'message' => 'Event fetched successfully',
            'data' => $data,
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

        $user = $request->user();
        if (!$user || $event->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $event = $this->eventService->update($event, $request->validated());

        $event->refresh();
        $subcategoryIds = is_array($event->subcategory_ids) ? $event->subcategory_ids : $event->subcategories()->pluck('id')->toArray();

        $data = [
            'id' => $event->id,
            'title' => $event->title,
            'event_type' => $event->event_type ?? 'free',
            'category_id' => $event->category_id,
            'subcategory_ids' => $subcategoryIds,
            'event_date' => $event->event_date?->format('Y-m-d'),
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'address' => $event->address,
            'description' => $event->description ?? null,
            'image_url' => $event->image_path ? MediaHelper::url($event->image_path) : null,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully',
            'data' => $data,
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

        $user = request()->user();
        if (!$user || ($event->user_id !== $user->id && !$user->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->eventService->delete($event);

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully',
        ]);
    }
}
