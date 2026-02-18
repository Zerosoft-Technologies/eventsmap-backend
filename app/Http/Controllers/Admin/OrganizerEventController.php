<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\MediaHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminEventOrganizerResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\EventOrganizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizerEventController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/admin/organizer/events
     *
     * List organizer events with pagination, search, and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string',
            'order' => 'nullable|string|in:asc,desc',
            'status' => 'nullable|string|in:draft,published,featured,cancelled,archived',
            'category_id' => 'nullable|integer|exists:categories,id',
            'organizer_id' => 'nullable|integer|exists:users,id',
            'search' => 'nullable|string|max:255',
            'start_date_from' => 'nullable|date',
            'start_date_to' => 'nullable|date',
            'is_featured' => 'nullable|boolean',
            'trashed' => 'nullable|boolean',
        ]);

        $query = EventOrganizer::query()
            ->with(['eventImages', 'talents']);

        // Include trashed if requested
        if ($request->boolean('trashed')) {
            $query->withTrashed();
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%")
                  ->orWhere('city', 'ILIKE', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $status = $request->input('status');
            switch ($status) {
                case 'published':
                    $query->where('is_published', true)->where('is_cancelled', false)->where('is_archived', false);
                    break;
                case 'featured':
                    $query->where('is_featured', true)->where('is_cancelled', false)->where('is_archived', false);
                    break;
                case 'cancelled':
                    $query->where('is_cancelled', true);
                    break;
                case 'archived':
                    $query->where('is_archived', true);
                    break;
                case 'draft':
                    $query->where('is_published', false)->where('is_cancelled', false)->where('is_archived', false);
                    break;
            }
        }

        // Category filter
        if ($request->filled('category_id')) {
            // If category_id is provided, convert to category string
            $category = \App\Models\Category::find($request->input('category_id'));
            if ($category) {
                $query->where('category', $category->name);
            }
        } elseif ($request->filled('category')) {
            // If category string is provided directly
            $query->where('category', $request->input('category'));
        }

        // Organizer filter
        if ($request->filled('organizer_id')) {
            $query->where('organizer_id', $request->input('organizer_id'));
        }

        // Featured filter
        if ($request->boolean('is_featured')) {
            $query->where('is_featured', true);
        }

        // Date range filter
        if ($request->filled('start_date_from')) {
            $query->where('start_datetime', '>=', $request->input('start_date_from'));
        }
        if ($request->filled('start_date_to')) {
            $query->where('start_datetime', '<=', $request->input('start_date_to'));
        }

        // Sorting
        $sort = $request->input('sort', 'created_at');
        $order = $request->input('order', 'desc');
        $query->orderBy($sort, $order);

        // Pagination
        $perPage = $request->input('per_page', 20);
        $events = $query->paginate($perPage);

        // Debug: Check if we have data
        if ($events->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No events found',
                'debug' => [
                    'query_sql' => $query->toSql(),
                    'total' => $events->total(),
                ]
            ]);
        }

        // Temporarily return raw data to test
        return response()->json([
            'success' => true,
            'data' => $events->items(), // Raw array without resource transformation
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'from' => $events->firstItem(),
                'to' => $events->lastItem(),
            ],
        ]);
    }

    /**
     * POST /api/admin/organizer/events
     *
     * Create new organizer event.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|min:3|max:200',
            'slug' => 'nullable|string|unique:events_organizer,slug|regex:/^[a-z0-9-]+$/',
            'status' => 'nullable|string|in:draft,published,featured,cancelled,archived',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'category_id' => 'nullable|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'category' => 'nullable|string|max:100',
            'price' => 'nullable|numeric|min:0',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'dresscode' => 'nullable|string|max:100',
            'age_restriction' => 'nullable|string|max:200',
            'min_age' => 'nullable|integer|min:0',
            'max_age' => 'nullable|integer|min:0',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'timezone' => 'nullable|string|max:50',
            'is_all_day' => 'nullable|boolean',
            'is_recurring' => 'nullable|boolean',
            'venue_name' => 'nullable|string|max:200',
            'venue_address' => 'nullable|string|max:500',
            'city' => 'required|string|max:100',
            'location_name' => 'nullable|string|max:200',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'organizer_name' => 'nullable|string|max:200',
            'organizer_id' => 'nullable|integer|exists:users,id',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_info' => 'nullable|array',
            'cover_image' => 'nullable|string|max:500',
            'video_url' => 'nullable|string|max:500',
            'about' => 'nullable|array',
            'location_details' => 'nullable|array',
            'booking' => 'nullable|array',
            'social_links' => 'nullable|array',
            'highlights' => 'nullable|array',
            'requirements' => 'nullable|array',
            'additional_info' => 'nullable|string',
            'accessibility_info' => 'nullable|string',
            'is_ticketed' => 'nullable|boolean',
            'is_free' => 'nullable|boolean',
            'capacity' => 'nullable|integer|min:0',
            'registration_url' => 'nullable|url|max:500',
            'registration_deadline' => 'nullable|date',
            'meta_keywords' => 'nullable|array',
            'custom_fields' => 'nullable|array',
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'tags' => 'nullable|array',
            'morning' => 'nullable|boolean',
            'afternoon' => 'nullable|boolean',
            'evening' => 'nullable|boolean',
            'night' => 'nullable|boolean',
        ]);

        // Generate slug if not provided
        if (!isset($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
            // Ensure uniqueness
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (EventOrganizer::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        // Map field names
        if (isset($validated['venue_address'])) {
            $validated['address'] = $validated['venue_address'];
            unset($validated['venue_address']);
        }
        
        // Convert category_id to category string if needed
        if (isset($validated['category_id'])) {
            // Get category name from category_id
            $category = \App\Models\Category::find($validated['category_id']);
            $validated['category'] = $category ? $category->name : 'Other';
            unset($validated['category_id']);
            unset($validated['subcategory_id']);
        }

        // Set static value for location_name if not provided
        if (empty($validated['location_name'])) {
            $validated['location_name'] = 'Default Location';
        }

        // Set status and related flags
        $status = $validated['status'] ?? 'draft';
        $validated['status'] = $status;
        
        $now = now();
        $validated['is_published'] = $status === 'published' || $status === 'featured';
        $validated['is_featured'] = $status === 'featured';
        $validated['is_cancelled'] = $status === 'cancelled';
        $validated['is_archived'] = $status === 'archived';
        
        // Set timestamps based on status
        if ($validated['is_published'] && !isset($validated['published_at'])) {
            $validated['published_at'] = $now;
        }
        if ($validated['is_featured'] && !isset($validated['featured_at'])) {
            $validated['featured_at'] = $now;
        }
        if ($validated['is_cancelled'] && !isset($validated['cancelled_at'])) {
            $validated['cancelled_at'] = $now;
        }
        if ($validated['is_archived'] && !isset($validated['archived_at'])) {
            $validated['archived_at'] = $now;
        }

        // Store coordinates before removing them from validated data
        $latitude = $validated['latitude'] ?? null;
        $longitude = $validated['longitude'] ?? null;

        // Remove latitude and longitude from validated data as they're not database columns
        unset($validated['latitude'], $validated['longitude']);

        // Set other defaults
        $validated['view_count'] = $validated['view_count'] ?? 0;

        $event = EventOrganizer::create($validated);

        // Set location if coordinates provided
        if (!empty($latitude) && !empty($longitude)) {
            $event->setLocationFromCoordinates($latitude, $longitude);
        }

        // Reload with relationships
        $event = EventOrganizer::query()
            ->withCoordinates()
            ->with(['eventImages', 'talents'])
            ->find($event->id);

        return response()->json([
            'success' => true,
            'data' => new AdminEventOrganizerResource($event),
            'message' => 'Organizer event created successfully',
        ], 201);
    }

    /**
     * GET /api/admin/organizer/events/{id}
     *
     * Get specific organizer event details.
     */
    public function show(int $id): JsonResponse
    {
        // Find the event or return null
        $event = EventOrganizer::query()
            ->with(['talents', 'eventImages'])
            ->withTrashed()
            ->find($id);

        // Check if event exists and return appropriate response
        if (!$event) {
            return $this->notFoundResponse('Organizer event');
        }

        return $this->successResponse(
            new AdminEventOrganizerResource($event)
        );
    }

    /**
     * PUT /api/admin/organizer/events/{id}
     *
     * Update organizer event.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $event = EventOrganizer::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|min:3|max:200',
            'slug' => 'nullable|string|regex:/^[a-z0-9-]+$/|unique:events_organizer,slug,' . $id,
            'status' => 'nullable|string|in:draft,published,featured,cancelled,archived',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'category_id' => 'nullable|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'category' => 'nullable|string|max:100',
            'price' => 'nullable|numeric|min:0',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'dresscode' => 'nullable|string|max:100',
            'age_restriction' => 'nullable|string|max:200',
            'min_age' => 'nullable|integer|min:0',
            'max_age' => 'nullable|integer|min:0',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'timezone' => 'nullable|string|max:50',
            'is_all_day' => 'nullable|boolean',
            'is_recurring' => 'nullable|boolean',
            'venue_name' => 'nullable|string|max:200',
            'address' => 'nullable|string|max:500',
            'city' => 'required|string|max:100',
            'location_name' => 'nullable|string|max:200',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'organizer_name' => 'nullable|string|max:200',
            'organizer_id' => 'nullable|integer|exists:users,id',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_info' => 'nullable|array',
            'cover_image' => 'nullable|string|max:500',
            'video_url' => 'nullable|string|max:500',
            'about' => 'nullable|array',
            'location_details' => 'nullable|array',
            'booking' => 'nullable|array',
            'social_links' => 'nullable|array',
            'highlights' => 'nullable|array',
            'requirements' => 'nullable|array',
            'additional_info' => 'nullable|string',
            'accessibility_info' => 'nullable|string',
            'is_ticketed' => 'nullable|boolean',
            'is_free' => 'nullable|boolean',
            'capacity' => 'nullable|integer|min:0',
            'registration_url' => 'nullable|url|max:500',
            'registration_deadline' => 'nullable|date',
            'meta_keywords' => 'nullable|array',
            'custom_fields' => 'nullable|array',
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'tags' => 'nullable|array',
            'morning' => 'nullable|boolean',
            'afternoon' => 'nullable|boolean',
            'evening' => 'nullable|boolean',
            'night' => 'nullable|boolean',
        ]);

        // Generate slug if title changed and slug not provided
        if (!isset($validated['slug']) && $validated['title'] !== $event->title) {
            $validated['slug'] = Str::slug($validated['title']);
            // Ensure uniqueness
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (EventOrganizer::where('slug', $validated['slug'])->where('id', '!=', $event->id)->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        // Map field names
        if (isset($validated['venue_address'])) {
            $validated['address'] = $validated['venue_address'];
            unset($validated['venue_address']);
        }
        
        // Convert category_id to category string if needed
        if (isset($validated['category_id'])) {
            // Get category name from category_id
            $category = \App\Models\Category::find($validated['category_id']);
            $validated['category'] = $category ? $category->name : 'Other';
            unset($validated['category_id']);
            unset($validated['subcategory_id']);
        }

        // Set static value for location_name if being set to null or empty
        if (isset($validated['location_name']) && empty($validated['location_name'])) {
            $validated['location_name'] = 'Default Location';
        }

        // Set status and related flags if status is provided
        if (isset($validated['status'])) {
            $status = $validated['status'];
            $now = now();
            
            // Set boolean flags based on status
            $validated['is_published'] = $status === 'published' || $status === 'featured';
            $validated['is_featured'] = $status === 'featured';
            $validated['is_cancelled'] = $status === 'cancelled';
            $validated['is_archived'] = $status === 'archived';
            
            // Set timestamps if changing to a new status
            if ($validated['is_published'] && !$event->published_at) {
                $validated['published_at'] = $now;
            }
            if ($validated['is_featured'] && !$event->featured_at) {
                $validated['featured_at'] = $now;
            }
            if ($validated['is_cancelled'] && !$event->cancelled_at) {
                $validated['cancelled_at'] = $now;
            }
            if ($validated['is_archived'] && !$event->archived_at) {
                $validated['archived_at'] = $now;
            }
        }

        // Store coordinates before removing them from validated data
        $latitude = $validated['latitude'] ?? null;
        $longitude = $validated['longitude'] ?? null;

        // Remove latitude and longitude from validated data as they're not database columns
        unset($validated['latitude'], $validated['longitude']);

        $event->update($validated);

        // Update location if coordinates provided
        if (!empty($latitude) && !empty($longitude)) {
            $event->setLocationFromCoordinates($latitude, $longitude);
        }

        // Reload with relationships
        $event = EventOrganizer::query()
            ->withCoordinates()
            ->with(['talents', 'eventImages'])
            ->find($event->id);

        return response()->json([
            'success' => true,
            'data' => new AdminEventOrganizerResource($event),
            'message' => 'Organizer event updated successfully',
        ]);
    }

    /**
     * DELETE /api/admin/organizer/events/{id}
     *
     * Delete organizer event (soft delete).
     */
    public function destroy(int $id): JsonResponse
    {
        $event = EventOrganizer::findOrFail($id);
        
        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Organizer event deleted successfully',
        ]);
    }
}
