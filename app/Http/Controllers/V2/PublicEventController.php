<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\EventResource;
use App\Models\EventV2;
use App\Services\V2\EventInvitedEntitiesService;
use App\Services\V2\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * PublicEventController - Public event feed for map display.
 *
 * Provides optimized, publicly accessible event listings with
 * geo-filtering support for map-based interfaces.
 */
class PublicEventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly EventInvitedEntitiesService $eventInvitedEntitiesService,
    ) {}

    /**
     * GET /api/v2/public/events
     *
     * Public event feed optimized for map display.
     * Only returns approved, non-suspended events that are upcoming or live.
     *
     * Supports:
     * - bbox: Bounding box filter (min_lat,max_lat,min_lng,max_lng)
     * - radius: Radius filter (lat,lng,radius_km)
     * - date_from/date_to: Date range
     * - category_id: Category filter
     * - entrance_status: Free/paid filter
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',

            // Bounding box filter
            'min_lat' => 'nullable|numeric|between:-90,90',
            'max_lat' => 'nullable|numeric|between:-90,90',
            'min_lng' => 'nullable|numeric|between:-180,180',
            'max_lng' => 'nullable|numeric|between:-180,180',

            // Radius filter
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:0.1|max:500',

            // Other filters
            'category_id' => 'nullable|integer|exists:categories,id',
            'entrance_status' => 'nullable|string|in:free,paid',
            // Date range (support multiple param names used by frontend)
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',

            // Sorting
            'sort' => 'nullable|string|in:event_date,distance',
            'order' => 'nullable|string|in:asc,desc',
        ]);

        // Build optimized query for public events
        $query = EventV2::query()
            ->select('events_v2.*')
            ->publicVisible()
            ->whereIn('status', [EventV2::STATUS_UPCOMING, EventV2::STATUS_LIVE])
            ->with(['category:id,name,slug', 'venue:id,name,slug,address']);

        // Bounding box filter (for map viewport)
        if ($request->filled(['min_lat', 'max_lat', 'min_lng', 'max_lng'])) {
            $query->withinBbox(
                $request->input('min_lat'),
                $request->input('max_lat'),
                $request->input('min_lng'),
                $request->input('max_lng')
            );
        }

        // Radius filter (for "events near me")
        if ($request->filled(['lat', 'lng', 'radius_km'])) {
            $lat = $request->input('lat');
            $lng = $request->input('lng');
            $radius = $request->input('radius_km');

            $query->withinRadius($lat, $lng, $radius)
                ->withDistance($lat, $lng);
        }

        // Category filter
        $query->when($request->filled('category_id'), function ($q) use ($request) {
            $q->where('category_id', $request->input('category_id'));
        });

        // Entrance status filter
        $query->when($request->filled('entrance_status'), function ($q) use ($request) {
            $q->where('entrance_status', $request->input('entrance_status'));
        });

        // Date range filter
        $dateFrom = $request->input('date_from')
            ?? $request->input('from_date')
            ?? $request->input('start_date');
        $dateTo = $request->input('date_to')
            ?? $request->input('to_date')
            ?? $request->input('end_date');

        $query->dateOverlap($dateFrom, $dateTo);

        // Sorting
        $sort = $request->input('sort', 'event_date');
        $order = $request->input('order', 'asc');

        if ($sort === 'distance' && $request->filled(['lat', 'lng'])) {
            $query->orderBy('distance_km', $order);
        } else {
            $query->orderBy($sort, $order);
        }

        // Pagination
        $perPage = $request->input('per_page', 20);
        $events = $query->paginate($perPage);

        $this->eventInvitedEntitiesService->hydrate($events->items());

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
     * GET /api/v2/public/events/{id}
     *
     * Get a single public event by ID (records view).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $event = EventV2::publicVisible()
            ->with(['category', 'venue', 'organisers', 'talents'])
            ->findOrFail($id);
        
        // Manually load subcategories from IDs
        $event->setRelation('subcategories', $event->subcategories_from_ids);

        // Record view
        $this->eventService->recordView(
            $event,
            $request->user(),
            $request->ip(),
            $request->userAgent(),
            $request->header('referer')
        );

        $this->eventInvitedEntitiesService->hydrate([$event]);

        return response()->json([
            'success' => true,
            'message' => 'Event fetched successfully',
            'data' => new EventResource($event),
        ]);
    }

    /**
     * GET /api/v2/public/events/{slug}
     *
     * Get a single public event by slug (records view).
     */
    public function showBySlug(Request $request, string $slug): JsonResponse
    {
        $event = EventV2::publicVisible()
            ->where('slug', $slug)
            ->with(['category', 'venue', 'organisers', 'talents'])
            ->firstOrFail();
        
        // Manually load subcategories from IDs
        $event->setRelation('subcategories', $event->subcategories_from_ids);

        // Record view
        $this->eventService->recordView(
            $event,
            $request->user(),
            $request->ip(),
            $request->userAgent(),
            $request->header('referer')
        );

        $this->eventInvitedEntitiesService->hydrate([$event]);

        return response()->json([
            'success' => true,
            'message' => 'Event fetched successfully',
            'data' => new EventResource($event),
        ]);
    }

    /**
     * GET /api/v2/public/events/map
     *
     * Get events optimized for map markers (minimal data).
     */
    public function map(Request $request): JsonResponse
    {
        $request->validate([
            'min_lat' => 'required|numeric|between:-90,90',
            'max_lat' => 'required|numeric|between:-90,90',
            'min_lng' => 'required|numeric|between:-180,180',
            'max_lng' => 'required|numeric|between:-180,180',
            'category_id' => 'nullable|integer|exists:categories,id',
            'limit' => 'nullable|integer|min:1|max:500',
            'zoom' => 'nullable|numeric|min:0|max:24',
            // Date range (support multiple param names used by frontend)
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $limit = $request->input('limit', 100);
        $dateFrom = $request->input('date_from')
            ?? $request->input('from_date')
            ?? $request->input('start_date');
        $dateTo = $request->input('date_to')
            ?? $request->input('to_date')
            ?? $request->input('end_date');
        $zoom = $request->input('zoom');

        $cacheKey = implode(':', array_filter([
            'publicEventsMap',
            $request->input('min_lat'),
            $request->input('max_lat'),
            $request->input('min_lng'),
            $request->input('max_lng'),
            $dateFrom ?? '',
            $dateTo ?? '',
            $request->input('category_id') ?? '',
            $zoom !== null ? (string) round((float) $zoom) : '',
            (string) $limit,
        ], fn ($v) => $v !== null));

        $events = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($request, $dateFrom, $dateTo, $limit) {
            return EventV2::query()
                ->select(['id', 'title', 'slug', 'latitude', 'longitude', 'event_date', 'start_time', 'category_id', 'entrance_status'])
                ->publicVisible()
                ->whereIn('status', [EventV2::STATUS_UPCOMING, EventV2::STATUS_LIVE])
                ->withinBbox(
                    $request->input('min_lat'),
                    $request->input('max_lat'),
                    $request->input('min_lng'),
                    $request->input('max_lng')
                )
                ->dateOverlap($dateFrom, $dateTo)
                ->when($request->filled('category_id'), function ($q) use ($request) {
                    $q->where('category_id', $request->input('category_id'));
                })
                ->orderBy('event_date', 'asc')
                ->limit($limit)
                ->get();
        });

        // Return minimal marker data
        $markers = $events->map(fn ($e) => [
            'id' => $e->id,
            'title' => $e->title,
            'slug' => $e->slug,
            'lat' => (float) $e->latitude,
            'lng' => (float) $e->longitude,
            'event_date' => $e->event_date->format('Y-m-d'),
            'start_time' => $e->start_time,
            'category_id' => $e->category_id,
            'entrance_status' => $e->entrance_status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Map markers fetched successfully',
            'data' => [
                'markers' => $markers,
                'count' => $markers->count(),
            ],
        ]);
    }
}
