<?php

namespace App\Http\Controllers\Organizer;

use App\Helpers\MediaHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Organizer\OrganizerEventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventController extends Controller
{
    /**
     * GET /api/organizer/events
     *
     * List organizer's events with pagination, search, and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string|in:created_at,start_datetime,title,view_count',
            'order' => 'nullable|string|in:asc,desc',
            'status' => 'nullable|string|in:draft,published,featured,cancelled,archived',
            'category_id' => 'nullable|integer|exists:categories,id',
            'search' => 'nullable|string|max:255',
            'start_date_from' => 'nullable|date',
            'start_date_to' => 'nullable|date',
            'is_featured' => 'nullable|boolean',
        ]);

        $query = Event::query()
            ->select('events.*')
            ->where('organizer_id', auth()->id())
            ->withCoordinates()
            ->with(['category:id,name,slug', 'subcategory:id,name,slug']);

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
            $query->where('category_id', $request->input('category_id'));
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
        $perPage = $request->input('per_page', 24);
        $events = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => OrganizerEventResource::collection($events),
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
     * POST /api/organizer/events
     *
     * Create new event.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|min:3|max:200',
            'slug' => 'nullable|string|unique:events,slug|regex:/^[a-z0-9-]+$/',
            'status' => 'nullable|string|in:draft,published,featured,cancelled,archived',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'category_id' => 'required|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
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
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'organizer_name' => 'nullable|string|max:200',
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
            while (Event::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        // Map field names
        if (isset($validated['venue_address'])) {
            $validated['address'] = $validated['venue_address'];
            unset($validated['venue_address']);
        }

        // Set organizer
        $validated['organizer_id'] = auth()->id();

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

        $event = Event::create($validated);

        // Set location if coordinates provided
        if (!empty($latitude) && !empty($longitude)) {
            $event->setLocationFromCoordinates($latitude, $longitude);
        }

        // Reload with relationships
        $event = Event::query()
            ->select('events.*')
            ->withCoordinates()
            ->with(['category', 'subcategory'])
            ->find($event->id);

        return response()->json([
            'success' => true,
            'data' => new OrganizerEventResource($event),
            'message' => 'Event created successfully',
        ], 201);
    }

    /**
     * GET /api/organizer/events/{id}
     *
     * Get specific event details.
     */
    public function show(int $id): JsonResponse
    {
        $event = Event::query()
            ->select('events.*')
            ->where('organizer_id', auth()->id())
            ->withCoordinates()
            ->with(['category', 'subcategory', 'talents', 'eventImages'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new OrganizerEventResource($event),
        ]);
    }

    /**
     * PUT /api/organizer/events/{id}
     *
     * Update event.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|min:3|max:200',
            'slug' => 'nullable|string|regex:/^[a-z0-9-]+$/|unique:events,slug,' . $id,
            'status' => 'nullable|string|in:draft,published,featured,cancelled,archived',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'category_id' => 'required|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
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
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'organizer_name' => 'nullable|string|max:200',
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
            while (Event::where('slug', $validated['slug'])->where('id', '!=', $event->id)->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        // Map field names
        if (isset($validated['venue_address'])) {
            $validated['address'] = $validated['venue_address'];
            unset($validated['venue_address']);
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
        $event = Event::query()
            ->select('events.*')
            ->withCoordinates()
            ->with(['category', 'subcategory', 'talents', 'eventImages'])
            ->find($event->id);

        return response()->json([
            'success' => true,
            'data' => new OrganizerEventResource($event),
            'message' => 'Event updated successfully',
        ]);
    }

    /**
     * DELETE /api/organizer/events/{id}
     *
     * Delete event (soft delete).
     */
    public function destroy(int $id): JsonResponse
    {
        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);
        
        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully',
        ]);
    }

    /**
     * POST /api/organizer/events/{id}/publish
     *
     * Publish event.
     */
    public function publish(int $id): JsonResponse
    {
        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);
        
        $event->update([
            'is_published' => true,
            'published_at' => now(),
            'is_cancelled' => false,
            'is_archived' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Event published successfully',
        ]);
    }

    /**
     * POST /api/organizer/events/{id}/unpublish
     *
     * Unpublish event.
     */
    public function unpublish(int $id): JsonResponse
    {
        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);
        
        $event->update([
            'is_published' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Event unpublished successfully',
        ]);
    }

    /**
     * POST /api/organizer/events/{id}/feature
     *
     * Feature event.
     */
    public function feature(int $id): JsonResponse
    {
        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);
        
        $event->update([
            'is_featured' => true,
            'featured_at' => now(),
            'is_published' => true,
            'published_at' => $event->published_at ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Event featured successfully',
        ]);
    }

    /**
     * POST /api/organizer/events/{id}/unfeature
     *
     * Unfeature event.
     */
    public function unfeature(int $id): JsonResponse
    {
        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);
        
        $event->update([
            'is_featured' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Event unfeatured successfully',
        ]);
    }

    /**
     * POST /api/organizer/events/{id}/cancel
     *
     * Cancel event.
     */
    public function cancel(int $id): JsonResponse
    {
        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);
        
        $event->update([
            'is_cancelled' => true,
            'cancelled_at' => now(),
            'is_published' => false,
            'is_featured' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Event cancelled successfully',
        ]);
    }

    /**
     * POST /api/organizer/events/{id}/archive
     *
     * Archive event.
     */
    public function archive(int $id): JsonResponse
    {
        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);
        
        $event->update([
            'is_archived' => true,
            'archived_at' => now(),
            'is_published' => false,
            'is_featured' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Event archived successfully',
        ]);
    }

    /**
     * POST /api/organizer/events/bulk/delete
     *
     * Delete multiple events.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'event_ids' => 'required|array',
            'event_ids.*' => 'required|integer|exists:events,id',
        ]);

        $deletedCount = Event::where('organizer_id', auth()->id())
            ->whereIn('id', $request->input('event_ids'))
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Successfully deleted {$deletedCount} event(s)",
        ]);
    }

    /**
     * POST /api/organizer/events/bulk/archive
     *
     * Archive multiple events.
     */
    public function bulkArchive(Request $request): JsonResponse
    {
        $request->validate([
            'event_ids' => 'required|array',
            'event_ids.*' => 'required|integer|exists:events,id',
        ]);

        $archivedCount = Event::where('organizer_id', auth()->id())
            ->whereIn('id', $request->input('event_ids'))
            ->update([
                'is_archived' => true,
                'archived_at' => now(),
                'is_published' => false,
                'is_featured' => false,
            ]);

        return response()->json([
            'success' => true,
            'message' => "Successfully archived {$archivedCount} event(s)",
        ]);
    }

    /**
     * GET /api/organizer/events/{id}/talents
     *
     * Get event talents.
     */
    public function getTalents(int $id): JsonResponse
    {
        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);
        
        $talents = $event->talents()
            ->withPivot(['role', 'sort_order'])
            ->orderBy('pivot_sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $talents->map(function ($talent) {
                return [
                    'id' => $talent->id,
                    'name' => $talent->name,
                    'slug' => $talent->slug,
                    'image' => $talent->image,
                    'role' => $talent->pivot->role ?? null,
                    'sort_order' => $talent->pivot->sort_order ?? 0,
                ];
            }),
        ]);
    }

    /**
     * POST /api/organizer/events/{id}/talents
     *
     * Attach talents to event.
     */
    public function attachTalents(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'talents' => 'required|array',
            'talents.*.id' => 'required|integer|exists:talents,id',
            'talents.*.role' => 'nullable|string|max:255',
            'talents.*.sort_order' => 'nullable|integer|min:0',
        ]);

        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);
        
        $syncData = [];
        foreach ($request->input('talents') as $talent) {
            $syncData[$talent['id']] = [
                'role' => $talent['role'] ?? null,
                'sort_order' => $talent['sort_order'] ?? 0,
            ];
        }

        $event->talents()->sync($syncData);

        return response()->json([
            'success' => true,
            'message' => 'Talents attached successfully',
        ]);
    }

    /**
     * DELETE /api/organizer/events/{id}/talents/{talentId}
     *
     * Detach talent from event.
     */
    public function detachTalent(int $id, int $talentId): JsonResponse
    {
        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);
        
        $event->talents()->detach($talentId);

        return response()->json([
            'success' => true,
            'message' => 'Talent detached successfully',
        ]);
    }

    /**
     * PATCH /api/organizer/events/{id}/talents/reorder
     *
     * Reorder event talents.
     */
    public function reorderTalents(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'talents' => 'required|array',
            'talents.*.id' => 'required|integer|exists:talents,id',
            'talents.*.sort_order' => 'required|integer|min:0',
        ]);

        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);
        
        foreach ($request->input('talents') as $talent) {
            $event->talents()->updateExistingPivot($talent['id'], [
                'sort_order' => $talent['sort_order'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Talents reordered successfully',
        ]);
    }

    /**
     * GET /api/organizer/events/{id}/media
     *
     * Get event media.
     */
    public function getMedia(int $id): JsonResponse
    {
        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);
        
        $media = $event->eventImages()
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $media->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => MediaHelper::url($image->url),
                    'alt_text' => $image->alt_text,
                    'caption' => $image->caption,
                    'is_primary' => $image->is_primary,
                    'sort_order' => $image->sort_order,
                ];
            }),
        ]);
    }

    /**
     * POST /api/organizer/events/{id}/media
     *
     * Attach media to event.
     */
    public function attachMedia(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'media_ids' => 'required|array',
            'media_ids.*' => 'required|integer|exists:media,id',
        ]);

        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);
        
        $syncData = [];
        $sortOrder = $event->eventImages()->max('sort_order') ?? 0;
        
        foreach ($request->input('media_ids') as $mediaId) {
            $syncData[$mediaId] = ['sort_order' => ++$sortOrder];
        }

        $event->eventImages()->syncWithoutDetaching($syncData);

        return response()->json([
            'success' => true,
            'message' => 'Media attached successfully',
        ]);
    }

    /**
     * DELETE /api/organizer/events/{id}/media/{mediaId}
     *
     * Detach media from event.
     */
    public function detachMedia(int $id, int $mediaId): JsonResponse
    {
        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);
        
        $event->eventImages()->detach($mediaId);

        return response()->json([
            'success' => true,
            'message' => 'Media detached successfully',
        ]);
    }

    /**
     * PATCH /api/organizer/events/{id}/media/reorder
     *
     * Reorder event media.
     */
    public function reorderMedia(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'media' => 'required|array',
            'media.*.id' => 'required|integer|exists:media,id',
            'media.*.sort_order' => 'required|integer|min:0',
        ]);

        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);
        
        foreach ($request->input('media') as $media) {
            $event->eventImages()->updateExistingPivot($media['id'], [
                'sort_order' => $media['sort_order'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Media reordered successfully',
        ]);
    }

    /**
     * POST /api/organizer/events/{id}/media/primary
     *
     * Set primary media for event.
     */
    public function setPrimaryMedia(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'media_id' => 'required|integer|exists:media,id',
        ]);

        $event = Event::where('organizer_id', auth()->id())->findOrFail($id);
        
        // Remove primary flag from all media
        $event->eventImages()->newPivotStatement()->where('event_id', $event->id)->update(['is_primary' => false]);
        
        // Set new primary
        $event->eventImages()->updateExistingPivot($request->input('media_id'), ['is_primary' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Primary media set successfully',
        ]);
    }
}
