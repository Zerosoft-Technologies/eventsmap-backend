<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminEventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventController extends Controller
{
    /**
     * GET /api/admin/events
     *
     * List events with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string',
            'status' => 'nullable|string|in:draft,published,featured,cancelled,archived',
            'category_id' => 'nullable|integer|exists:categories,id',
            'search' => 'nullable|string|max:255',
            'start_date_from' => 'nullable|date',
            'start_date_to' => 'nullable|date',
            'is_featured' => 'nullable|boolean',
            'trashed' => 'nullable|boolean',
        ]);

        $query = Event::query()
            ->select('events.*')
            ->withCoordinates()
            ->with(['category:id,name,slug', 'subcategory:id,name,slug']);

        // Include trashed if requested
        if ($request->boolean('trashed')) {
            $query->withTrashed();
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $status = $request->input('status');
            switch ($status) {
                case 'draft':
                    $query->where('is_published', false)->where('is_cancelled', false)->where('is_archived', false);
                    break;
                case 'published':
                    $query->where('is_published', true)->where('is_featured', false)->where('is_cancelled', false)->where('is_archived', false);
                    break;
                case 'featured':
                    $query->where('is_featured', true)->where('is_cancelled', false)->where('is_archived', false);
                    break;
                case 'cancelled':
                    $query->where('is_cancelled', true)->where('is_archived', false);
                    break;
                case 'archived':
                    $query->where('is_archived', true);
                    break;
            }
        }

        // Category filter
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Date range filter
        if ($request->filled('start_date_from')) {
            $query->where('start_datetime', '>=', $request->input('start_date_from'));
        }
        if ($request->filled('start_date_to')) {
            $query->where('start_datetime', '<=', $request->input('start_date_to'));
        }

        // Featured filter
        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        // Sorting
        $sortField = 'created_at';
        $sortDirection = 'desc';
        if ($request->filled('sort')) {
            $sort = $request->input('sort');
            if (str_starts_with($sort, '-')) {
                $sortDirection = 'desc';
                $sortField = substr($sort, 1);
            } else {
                $sortDirection = 'asc';
                $sortField = $sort;
            }
        }
        $allowedSorts = ['title', 'created_at', 'updated_at', 'start_datetime', 'price', 'view_count'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        }

        // Pagination
        $perPage = $request->input('per_page', 20);
        $events = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => AdminEventResource::collection($events),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'total_pages' => $events->lastPage(),
                'has_more' => $events->hasMorePages(),
            ],
        ]);
    }

    /**
     * GET /api/admin/events/{id}
     *
     * Get single event with all details.
     */
    public function show(int $id): JsonResponse
    {
        $event = Event::query()
            ->select('events.*')
            ->withCoordinates()
            ->with(['category', 'subcategory', 'talents', 'eventImages'])
            ->withTrashed()
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new AdminEventResource($event),
        ]);
    }

    /**
     * POST /api/admin/events
     *
     * Create a new event.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|min:3|max:200',
            'slug' => 'nullable|string|unique:events,slug|regex:/^[a-z0-9-]+$/',
            'description' => 'nullable|string',
            'category_id' => 'required|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'price' => 'nullable|numeric|min:0',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'dresscode' => 'nullable|string|max:100',
            'min_age' => 'nullable|integer|min:0',
            'max_age' => 'nullable|integer|min:0',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'timezone' => 'nullable|string|max:50',
            'venue_name' => 'nullable|string|max:200',
            'address' => 'nullable|string|max:500',
            'city' => 'required|string|max:100',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'organizer_name' => 'nullable|string|max:200',
            'organizer_id' => 'nullable|integer|exists:users,id',
            'contact_info' => 'nullable|array',
            'cover_image' => 'nullable|string|max:500',
            'video_url' => 'nullable|string|max:500',
            'about' => 'nullable|array',
            'location_details' => 'nullable|array',
            'booking' => 'nullable|array',
            'social_links' => 'nullable|array',
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'tags' => 'nullable|array',
            'morning' => 'nullable|boolean',
            'afternoon' => 'nullable|boolean',
            'evening' => 'nullable|boolean',
            'night' => 'nullable|boolean',
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
            // Ensure uniqueness
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (Event::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        // Set defaults
        $validated['is_published'] = false;
        $validated['is_featured'] = false;
        $validated['is_cancelled'] = false;
        $validated['is_archived'] = false;
        $validated['view_count'] = 0;

        $event = Event::create($validated);

        // Set location if coordinates provided
        if (!empty($validated['latitude']) && !empty($validated['longitude'])) {
            $event->setLocationFromCoordinates($validated['latitude'], $validated['longitude']);
        }

        // Reload with relationships
        $event = Event::query()
            ->select('events.*')
            ->withCoordinates()
            ->with(['category', 'subcategory'])
            ->find($event->id);

        return response()->json([
            'success' => true,
            'data' => new AdminEventResource($event),
        ], 201);
    }

    /**
     * PUT /api/admin/events/{id}
     *
     * Update an event (full update).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $event = Event::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|min:3|max:200',
            'slug' => 'nullable|string|regex:/^[a-z0-9-]+$/|unique:events,slug,' . $id,
            'description' => 'nullable|string',
            'category_id' => 'required|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'price' => 'nullable|numeric|min:0',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'dresscode' => 'nullable|string|max:100',
            'min_age' => 'nullable|integer|min:0',
            'max_age' => 'nullable|integer|min:0',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'timezone' => 'nullable|string|max:50',
            'venue_name' => 'nullable|string|max:200',
            'address' => 'nullable|string|max:500',
            'city' => 'required|string|max:100',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'organizer_name' => 'nullable|string|max:200',
            'organizer_id' => 'nullable|integer|exists:users,id',
            'contact_info' => 'nullable|array',
            'cover_image' => 'nullable|string|max:500',
            'video_url' => 'nullable|string|max:500',
            'about' => 'nullable|array',
            'location_details' => 'nullable|array',
            'booking' => 'nullable|array',
            'social_links' => 'nullable|array',
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'tags' => 'nullable|array',
            'morning' => 'nullable|boolean',
            'afternoon' => 'nullable|boolean',
            'evening' => 'nullable|boolean',
            'night' => 'nullable|boolean',
        ]);

        $event->update($validated);

        // Update location if coordinates provided
        if (!empty($validated['latitude']) && !empty($validated['longitude'])) {
            $event->setLocationFromCoordinates($validated['latitude'], $validated['longitude']);
        }

        // Reload with relationships
        $event = Event::query()
            ->select('events.*')
            ->withCoordinates()
            ->with(['category', 'subcategory', 'talents', 'eventImages'])
            ->find($event->id);

        return response()->json([
            'success' => true,
            'data' => new AdminEventResource($event),
        ]);
    }

    /**
     * PATCH /api/admin/events/{id}
     *
     * Partial update an event.
     */
    public function partialUpdate(Request $request, int $id): JsonResponse
    {
        $event = Event::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|min:3|max:200',
            'slug' => 'sometimes|string|regex:/^[a-z0-9-]+$/|unique:events,slug,' . $id,
            'description' => 'sometimes|nullable|string',
            'category_id' => 'sometimes|integer|exists:categories,id',
            'subcategory_id' => 'sometimes|nullable|integer|exists:subcategories,id',
            'price' => 'sometimes|nullable|numeric|min:0',
            'min_price' => 'sometimes|nullable|numeric|min:0',
            'max_price' => 'sometimes|nullable|numeric|min:0',
            'currency' => 'sometimes|nullable|string|size:3',
            'dresscode' => 'sometimes|nullable|string|max:100',
            'min_age' => 'sometimes|nullable|integer|min:0',
            'max_age' => 'sometimes|nullable|integer|min:0',
            'start_datetime' => 'sometimes|date',
            'end_datetime' => 'sometimes|date',
            'timezone' => 'sometimes|nullable|string|max:50',
            'venue_name' => 'sometimes|nullable|string|max:200',
            'address' => 'sometimes|nullable|string|max:500',
            'city' => 'sometimes|string|max:100',
            'country' => 'sometimes|nullable|string|max:100',
            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'organizer_name' => 'sometimes|nullable|string|max:200',
            'organizer_id' => 'sometimes|nullable|integer|exists:users,id',
            'contact_info' => 'sometimes|nullable|array',
            'cover_image' => 'sometimes|nullable|string|max:500',
            'video_url' => 'sometimes|nullable|string|max:500',
            'about' => 'sometimes|nullable|array',
            'location_details' => 'sometimes|nullable|array',
            'booking' => 'sometimes|nullable|array',
            'social_links' => 'sometimes|nullable|array',
            'meta_title' => 'sometimes|nullable|string|max:70',
            'meta_description' => 'sometimes|nullable|string|max:160',
            'tags' => 'sometimes|nullable|array',
            'morning' => 'sometimes|nullable|boolean',
            'afternoon' => 'sometimes|nullable|boolean',
            'evening' => 'sometimes|nullable|boolean',
            'night' => 'sometimes|nullable|boolean',
        ]);

        $event->update($validated);

        // Update location if coordinates provided
        if (isset($validated['latitude']) && isset($validated['longitude'])) {
            $event->setLocationFromCoordinates($validated['latitude'], $validated['longitude']);
        }

        // Reload with relationships
        $event = Event::query()
            ->select('events.*')
            ->withCoordinates()
            ->with(['category', 'subcategory', 'talents', 'eventImages'])
            ->find($event->id);

        return response()->json([
            'success' => true,
            'data' => new AdminEventResource($event),
        ]);
    }

    /**
     * DELETE /api/admin/events/{id}
     *
     * Soft delete an event.
     */
    public function destroy(int $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        $event->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Event deleted successfully.',
            ],
        ]);
    }

    /**
     * POST /api/admin/events/{id}/restore
     *
     * Restore a soft-deleted event.
     */
    public function restore(int $id): JsonResponse
    {
        $event = Event::withTrashed()->findOrFail($id);

        if (!$event->trashed()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_DELETED',
                    'message' => 'Event is not deleted.',
                ],
            ], 400);
        }

        $event->restore();

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Event restored successfully.',
            ],
        ]);
    }

    /**
     * POST /api/admin/events/{id}/duplicate
     *
     * Duplicate an event.
     */
    public function duplicate(int $id): JsonResponse
    {
        $event = Event::withTrashed()->findOrFail($id);

        $newEvent = $event->replicate([
            'slug',
            'view_count',
            'published_at',
            'featured_at',
            'cancelled_at',
            'archived_at',
            'deleted_at',
        ]);

        $newEvent->title = $event->title . ' (Copy)';
        $newEvent->slug = Str::slug($newEvent->title);
        $newEvent->is_published = false;
        $newEvent->is_featured = false;
        $newEvent->is_cancelled = false;
        $newEvent->is_archived = false;
        $newEvent->view_count = 0;

        // Ensure unique slug
        $baseSlug = $newEvent->slug;
        $counter = 1;
        while (Event::where('slug', $newEvent->slug)->exists()) {
            $newEvent->slug = $baseSlug . '-' . $counter++;
        }

        $newEvent->save();

        // Copy talents
        foreach ($event->talents as $talent) {
            $newEvent->talents()->attach($talent->id, [
                'role' => $talent->pivot->role,
                'sort_order' => $talent->pivot->sort_order,
            ]);
        }

        // Reload with relationships
        $newEvent = Event::query()
            ->select('events.*')
            ->withCoordinates()
            ->with(['category', 'subcategory', 'talents'])
            ->find($newEvent->id);

        return response()->json([
            'success' => true,
            'data' => new AdminEventResource($newEvent),
        ], 201);
    }

    /**
     * POST /api/admin/events/{id}/publish
     *
     * Publish an event.
     */
    public function publish(int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        if ($event->is_published && !$event->is_cancelled) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ALREADY_PUBLISHED',
                    'message' => 'Event is already published.',
                ],
            ], 400);
        }

        $event->update([
            'is_published' => true,
            'is_cancelled' => false,
            'is_archived' => false,
            'published_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Event published successfully.',
            ],
        ]);
    }

    /**
     * POST /api/admin/events/{id}/unpublish
     *
     * Unpublish an event (return to draft).
     */
    public function unpublish(int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $event->update([
            'is_published' => false,
            'is_featured' => false,
            'published_at' => null,
            'featured_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Event unpublished successfully.',
            ],
        ]);
    }

    /**
     * POST /api/admin/events/{id}/feature
     *
     * Feature an event.
     */
    public function feature(int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        if (!$event->is_published) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_PUBLISHED',
                    'message' => 'Event must be published before featuring.',
                ],
            ], 400);
        }

        $event->update([
            'is_featured' => true,
            'featured_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Event featured successfully.',
            ],
        ]);
    }

    /**
     * POST /api/admin/events/{id}/unfeature
     *
     * Remove feature from an event.
     */
    public function unfeature(int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $event->update([
            'is_featured' => false,
            'featured_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Event unfeatured successfully.',
            ],
        ]);
    }

    /**
     * POST /api/admin/events/{id}/cancel
     *
     * Cancel an event.
     */
    public function cancel(int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $event->update([
            'is_cancelled' => true,
            'is_featured' => false,
            'cancelled_at' => now(),
            'featured_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Event cancelled successfully.',
            ],
        ]);
    }

    /**
     * POST /api/admin/events/{id}/archive
     *
     * Archive an event.
     */
    public function archive(int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $event->update([
            'is_archived' => true,
            'is_published' => false,
            'is_featured' => false,
            'archived_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Event archived successfully.',
            ],
        ]);
    }

    /**
     * GET /api/admin/events/{id}/talents
     *
     * Get talents attached to an event.
     */
    public function getTalents(int $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        $talents = $event->talents()->get();

        return response()->json([
            'success' => true,
            'data' => $talents->map(function ($talent) {
                return [
                    'id' => $talent->id,
                    'name' => $talent->name,
                    'slug' => $talent->slug,
                    'image' => $talent->image,
                    'role' => $talent->pivot->role,
                    'sort_order' => $talent->pivot->sort_order,
                ];
            }),
        ]);
    }

    /**
     * POST /api/admin/events/{id}/talents
     *
     * Attach talents to an event.
     */
    public function attachTalents(Request $request, int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'talents' => 'required|array',
            'talents.*.id' => 'required|integer|exists:talents,id',
            'talents.*.role' => 'nullable|string|max:100',
            'talents.*.sort_order' => 'nullable|integer|min:1',
        ]);

        $syncData = [];
        foreach ($validated['talents'] as $index => $talentData) {
            $syncData[$talentData['id']] = [
                'role' => $talentData['role'] ?? null,
                'sort_order' => $talentData['sort_order'] ?? ($index + 1),
            ];
        }

        $event->talents()->syncWithoutDetaching($syncData);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Talents attached successfully.',
            ],
        ]);
    }

    /**
     * DELETE /api/admin/events/{id}/talents/{talentId}
     *
     * Detach a talent from an event.
     */
    public function detachTalent(int $id, int $talentId): JsonResponse
    {
        $event = Event::findOrFail($id);
        $event->talents()->detach($talentId);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Talent detached successfully.',
            ],
        ]);
    }

    /**
     * PATCH /api/admin/events/{id}/talents/reorder
     *
     * Reorder talents in an event.
     */
    public function reorderTalents(Request $request, int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:talents,id',
            'items.*.sort_order' => 'required|integer|min:1',
        ]);

        foreach ($validated['items'] as $item) {
            $event->talents()->updateExistingPivot($item['id'], [
                'sort_order' => $item['sort_order'],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Talents reordered successfully.',
            ],
        ]);
    }

    /**
     * GET /api/admin/events/{id}/media
     *
     * Get media attached to an event.
     */
    public function getMedia(int $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        $images = $event->eventImages()->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => $image->url,
                    'alt_text' => $image->alt_text,
                    'caption' => $image->caption,
                    'is_primary' => $image->is_primary,
                    'sort_order' => $image->sort_order,
                ];
            }),
        ]);
    }

    /**
     * POST /api/admin/events/{id}/media
     *
     * Attach media to an event.
     */
    public function attachMedia(Request $request, int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'media' => 'required|array',
            'media.*.url' => 'required|string|max:500',
            'media.*.alt_text' => 'nullable|string|max:200',
            'media.*.caption' => 'nullable|string|max:500',
            'media.*.is_primary' => 'nullable|boolean',
            'media.*.sort_order' => 'nullable|integer|min:0',
        ]);

        $maxOrder = $event->eventImages()->max('sort_order') ?? -1;

        foreach ($validated['media'] as $index => $mediaData) {
            // If this is primary, unset others
            if (!empty($mediaData['is_primary'])) {
                $event->eventImages()->update(['is_primary' => false]);
            }

            $event->eventImages()->create([
                'url' => $mediaData['url'],
                'alt_text' => $mediaData['alt_text'] ?? null,
                'caption' => $mediaData['caption'] ?? null,
                'is_primary' => $mediaData['is_primary'] ?? false,
                'sort_order' => $mediaData['sort_order'] ?? ($maxOrder + $index + 1),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Media attached successfully.',
            ],
        ]);
    }

    /**
     * DELETE /api/admin/events/{id}/media/{mediaId}
     *
     * Detach media from an event.
     */
    public function detachMedia(int $id, int $mediaId): JsonResponse
    {
        $event = Event::findOrFail($id);
        $event->eventImages()->where('id', $mediaId)->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Media removed successfully.',
            ],
        ]);
    }

    /**
     * PATCH /api/admin/events/{id}/media/reorder
     *
     * Reorder media in an event.
     */
    public function reorderMedia(Request $request, int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            $event->eventImages()->where('id', $item['id'])->update([
                'sort_order' => $item['sort_order'],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Media reordered successfully.',
            ],
        ]);
    }

    /**
     * POST /api/admin/events/{id}/media/primary
     *
     * Set primary image for an event.
     */
    public function setPrimaryMedia(Request $request, int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'media_id' => 'required|integer',
        ]);

        // Unset all as non-primary
        $event->eventImages()->update(['is_primary' => false]);

        // Set the selected one as primary
        $event->eventImages()->where('id', $validated['media_id'])->update(['is_primary' => true]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Primary image set successfully.',
            ],
        ]);
    }
}
