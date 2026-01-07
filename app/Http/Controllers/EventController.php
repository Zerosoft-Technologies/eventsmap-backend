<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Http\Resources\EventResource;
use App\Http\Resources\TalentResource;
use App\Http\Resources\EventImageResource;
use App\Http\Resources\EventAboutResource;
use App\Http\Resources\EventLocationResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    /**
     * GET /events
     *
     * List events with optional filters.
     *
     * Supported filters (all optional, chainable):
     * - search : Search in title and description
     * - lat (float) + lng (float) + radius (km) : Geo filter using ST_DWithin
     * - from_date : Filter events starting from this date
     * - to_date : Filter events starting up to this date
     * - min_price : Minimum price filter
     * - max_price : Maximum price filter
     * - category : Filter by category slug
     * - subcategory : Filter by subcategory slug
     * - live_now=true : Only currently live events
     * - page : Page number for pagination
     * - per_page : Items per page (default 20, max 100)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:0.1|max:500',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'category' => 'nullable|string|max:100',
            'subcategory' => 'nullable|string|max:100',
            'live_now' => 'nullable|string|in:true,false,1,0',
            'sessions' => 'nullable|string',
            'morning' => 'nullable|string|in:true,false,1,0',
            'afternoon' => 'nullable|string|in:true,false,1,0',
            'evening' => 'nullable|string|in:true,false,1,0',
            'night' => 'nullable|string|in:true,false,1,0',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Start building query with coordinates
        $query = Event::query()
            ->select('events.*')
            ->withCoordinates()
            ->with(['category:id,name,slug', 'subcategory:id,name,slug', 'talents:id,name,image'])
            ->published();

        // Search filtering: search in title and description
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'ILIKE', "%{$searchTerm}%")
                ->orWhere('description', 'ILIKE', "%{$searchTerm}%");
            });
        }

        // Geo filtering: lat, lng, radius (all three required for geo query)
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $radius = $request->input('radius');

        if ($lat !== null && $lng !== null && $radius !== null) {
            $query->withinRadius((float) $lat, (float) $lng, (float) $radius)
                  ->withDistanceFrom((float) $lat, (float) $lng)
                  ->orderBy('distance_meters', 'asc');
        }

        // Date range filtering
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        if ($fromDate || $toDate) {
            $query->dateRange($fromDate, $toDate);
        }

        // Price range filtering
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        if ($minPrice !== null || $maxPrice !== null) {
            $query->priceRange(
                $minPrice !== null ? (float) $minPrice : null,
                $maxPrice !== null ? (float) $maxPrice : null
            );
        }

        // Category filtering
        if ($request->filled('category')) {
            $query->category($request->input('category'));
        }

        // Subcategory filtering
        if ($request->filled('subcategory')) {
            $query->subcategory($request->input('subcategory'));
        }

        // Session filtering - handle comma-separated sessions parameter
        if ($request->filled('sessions')) {
            $sessions = explode(',', $request->input('sessions'));
            $validSessions = ['morning', 'afternoon', 'evening', 'night'];
            
            // Filter for valid session names
            $sessions = array_intersect($sessions, $validSessions);
            
            if (!empty($sessions)) {
                // Build where clause for multiple sessions
                $query->where(function ($q) use ($sessions) {
                    foreach ($sessions as $session) {
                        $q->orWhere($session, true);
                    }
                });
            }
        } else {
            // Fallback to individual boolean parameters
            if ($request->filled('morning')) {
                $query->where('morning', $request->boolean('morning'));
            }
            if ($request->filled('afternoon')) {
                $query->where('afternoon', $request->boolean('afternoon'));
            }
            if ($request->filled('evening')) {
                $query->where('evening', $request->boolean('evening'));
            }
            if ($request->filled('night')) {
                $query->where('night', $request->boolean('night'));
            }
        }

        // Live now filtering
        if ($request->input('live_now') === 'true' || $request->input('live_now') === '1') {
            $query->liveNow();
        }

        // Default ordering if no geo filter (most recent first)
        if ($lat === null || $lng === null || $radius === null) {
            $query->orderBy('start_datetime', 'asc');
        }

        // Paginate results
        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);
        $events = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform response to include is_live_now, format distance, and include talents
        $events->getCollection()->transform(function ($event) {
            $data = $event->toArray();

            // Convert distance to km if present
            if (isset($data['distance_meters'])) {
                $data['distance_km'] = round($data['distance_meters'] / 1000, 2);
            }

            // Format talents data
            if (isset($data['talents'])) {
                $data['talents'] = collect($data['talents'])->map(function ($talent) {
                    return [
                        'id' => $talent['id'],
                        'name' => $talent['name'],
                        'image' => $talent['image'],
                        'role' => $talent['pivot']['role'] ?? null,
                        'sort_order' => $talent['pivot']['sort_order'] ?? 0,
                    ];
                })->sortBy('sort_order')->values()->all();
            }

            return $data;
        });

        return response()->json([
            'success' => true,
            'data' => $events->items(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * GET /events/{id}
     *
     * Get a single event by ID with full details.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $event = Event::query()
            ->select('events.*')
            ->withCoordinates()
            ->with([
                'category:id,name,slug',
                'subcategory:id,name,slug',
                'talents',
                'eventImages',
            ])
            ->published()
            ->notCancelled()
            ->findOrFail($id);

        // Increment view count
        $event->incrementViewCount();

        return response()->json([
            'success' => true,
            'data' => new EventResource($event),
        ]);
    }

    /**
     * GET /events/{id}/talents
     *
     * Get talents for a specific event.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function talents(int $id): JsonResponse
    {
        $event = Event::query()
            ->published()
            ->notCancelled()
            ->findOrFail($id);

        $talents = $event->talents()->get();

        return response()->json([
            'success' => true,
            'data' => TalentResource::collection($talents),
        ]);
    }

    /**
     * GET /events/{id}/about
     *
     * Get about information for a specific event.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function about(int $id): JsonResponse
    {
        $event = Event::query()
            ->published()
            ->notCancelled()
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new EventAboutResource($event),
        ]);
    }

    /**
     * GET /events/{id}/location
     *
     * Get location details for a specific event.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function location(int $id): JsonResponse
    {
        $event = Event::query()
            ->select('events.*')
            ->withCoordinates()
            ->published()
            ->notCancelled()
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new EventLocationResource($event),
        ]);
    }

    /**
     * GET /events/{id}/images
     *
     * Get images for a specific event.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function images(int $id): JsonResponse
    {
        $event = Event::query()
            ->published()
            ->notCancelled()
            ->findOrFail($id);

        $images = $event->eventImages()->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => EventImageResource::collection($images),
        ]);
    }
}
