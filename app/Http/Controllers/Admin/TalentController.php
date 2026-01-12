<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Talent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TalentController extends Controller
{
    /**
     * GET /api/admin/talents
     *
     * List talents with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string',
            'search' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'type' => 'nullable|string',
            'trashed' => 'nullable|boolean',
        ]);

        $query = Talent::query();

        // Include trashed if requested
        if ($request->boolean('trashed')) {
            $query->withTrashed();
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('bio', 'ILIKE', "%{$search}%");
            });
        }

        // Active filter
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Type filter
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Sorting
        $sortField = 'name';
        $sortDirection = 'asc';
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
        $allowedSorts = ['name', 'created_at', 'updated_at', 'type'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        }

        // Pagination
        $perPage = $request->input('per_page', 20);
        $talents = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $talents->map(function ($talent) {
                return [
                    'id' => $talent->id,
                    'name' => $talent->name,
                    'slug' => $talent->slug,
                    'type' => $talent->type,
                    'short_bio' => $talent->short_bio,
                    'image' => $talent->image,
                    'is_active' => $talent->is_active,
                    'events_count' => $talent->events()->count(),
                    'created_at' => $talent->created_at?->toIso8601String(),
                    'updated_at' => $talent->updated_at?->toIso8601String(),
                    'deleted_at' => $talent->deleted_at?->toIso8601String(),
                ];
            }),
            'pagination' => [
                'current_page' => $talents->currentPage(),
                'per_page' => $talents->perPage(),
                'total' => $talents->total(),
                'total_pages' => $talents->lastPage(),
                'has_more' => $talents->hasMorePages(),
            ],
        ]);
    }

    /**
     * GET /api/admin/talents/{id}
     *
     * Get single talent with all details.
     */
    public function show(int $id): JsonResponse
    {
        $talent = Talent::withTrashed()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $talent->id,
                'name' => $talent->name,
                'slug' => $talent->slug,
                'type' => $talent->type,
                'short_bio' => $talent->short_bio,
                'bio' => $talent->bio,
                'image' => $talent->image,
                'social_links' => $talent->social_links,
                'is_active' => $talent->is_active,
                'events_count' => $talent->events()->count(),
                'created_at' => $talent->created_at?->toIso8601String(),
                'updated_at' => $talent->updated_at?->toIso8601String(),
                'deleted_at' => $talent->deleted_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/admin/talents
     *
     * Create a new talent.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:200',
            'slug' => 'nullable|string|unique:talents,slug|regex:/^[a-z0-9-]+$/',
            'type' => 'nullable|string|in:artist,speaker,performer,dj,band,other',
            'short_bio' => 'nullable|string|max:300',
            'bio' => 'nullable|string|max:5000',
            'image' => 'nullable|string|max:500',
            'social_links' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            // Ensure uniqueness
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (Talent::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        // Set defaults
        $validated['type'] = $validated['type'] ?? 'artist';
        $validated['is_active'] = $validated['is_active'] ?? true;

        $talent = Talent::create($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $talent->id,
                'name' => $talent->name,
                'slug' => $talent->slug,
                'type' => $talent->type,
                'short_bio' => $talent->short_bio,
                'bio' => $talent->bio,
                'image' => $talent->image,
                'social_links' => $talent->social_links,
                'is_active' => $talent->is_active,
                'created_at' => $talent->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * PUT /api/admin/talents/{id}
     *
     * Update a talent (full update).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $talent = Talent::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|min:2|max:200',
            'slug' => 'nullable|string|regex:/^[a-z0-9-]+$/|unique:talents,slug,' . $id,
            'type' => 'nullable|string|in:artist,speaker,performer,dj,band,other',
            'short_bio' => 'nullable|string|max:300',
            'bio' => 'nullable|string|max:5000',
            'image' => 'nullable|string|max:500',
            'social_links' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $talent->update($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $talent->id,
                'name' => $talent->name,
                'slug' => $talent->slug,
                'type' => $talent->type,
                'short_bio' => $talent->short_bio,
                'bio' => $talent->bio,
                'image' => $talent->image,
                'social_links' => $talent->social_links,
                'is_active' => $talent->is_active,
                'updated_at' => $talent->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * PATCH /api/admin/talents/{id}
     *
     * Partial update a talent.
     */
    public function partialUpdate(Request $request, int $id): JsonResponse
    {
        $talent = Talent::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|min:2|max:200',
            'slug' => 'sometimes|string|regex:/^[a-z0-9-]+$/|unique:talents,slug,' . $id,
            'type' => 'sometimes|string|in:artist,speaker,performer,dj,band,other',
            'short_bio' => 'sometimes|nullable|string|max:300',
            'bio' => 'sometimes|nullable|string|max:5000',
            'image' => 'sometimes|nullable|string|max:500',
            'social_links' => 'sometimes|nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $talent->update($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $talent->id,
                'name' => $talent->name,
                'slug' => $talent->slug,
                'type' => $talent->type,
                'short_bio' => $talent->short_bio,
                'bio' => $talent->bio,
                'image' => $talent->image,
                'social_links' => $talent->social_links,
                'is_active' => $talent->is_active,
                'updated_at' => $talent->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * DELETE /api/admin/talents/{id}
     *
     * Soft delete a talent.
     */
    public function destroy(int $id): JsonResponse
    {
        $talent = Talent::findOrFail($id);

        // Check if talent is attached to published events
        $publishedEventsCount = $talent->events()->where('is_published', true)->count();
        if ($publishedEventsCount > 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DEPENDENCY_ERROR',
                    'message' => "Cannot delete talent. It is attached to {$publishedEventsCount} published event(s).",
                ],
            ], 409);
        }

        $talent->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Talent deleted successfully.',
            ],
        ]);
    }

    /**
     * POST /api/admin/talents/{id}/restore
     *
     * Restore a soft-deleted talent.
     */
    public function restore(int $id): JsonResponse
    {
        $talent = Talent::withTrashed()->findOrFail($id);

        if (!$talent->trashed()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_DELETED',
                    'message' => 'Talent is not deleted.',
                ],
            ], 400);
        }

        $talent->restore();

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Talent restored successfully.',
            ],
        ]);
    }

    /**
     * POST /api/admin/talents/{id}/activate
     *
     * Activate a talent.
     */
    public function activate(int $id): JsonResponse
    {
        $talent = Talent::findOrFail($id);
        $talent->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Talent activated successfully.',
            ],
        ]);
    }

    /**
     * POST /api/admin/talents/{id}/deactivate
     *
     * Deactivate a talent.
     */
    public function deactivate(int $id): JsonResponse
    {
        $talent = Talent::findOrFail($id);

        // Warn if attached to published events
        $publishedEventsCount = $talent->events()->where('is_published', true)->count();

        $talent->update(['is_active' => false]);

        $message = 'Talent deactivated successfully.';
        if ($publishedEventsCount > 0) {
            $message .= " Warning: This talent is attached to {$publishedEventsCount} published event(s).";
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => $message,
            ],
        ]);
    }

    /**
     * GET /api/admin/talents/{id}/events
     *
     * Get events for a talent.
     */
    public function getEvents(int $id): JsonResponse
    {
        $talent = Talent::findOrFail($id);
        $events = $talent->events()
            ->select('events.id', 'events.title', 'events.slug', 'events.start_datetime', 'events.is_published', 'events.is_featured')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'start_datetime' => $event->start_datetime?->toIso8601String(),
                    'is_published' => $event->is_published,
                    'is_featured' => $event->is_featured,
                    'role' => $event->pivot->role,
                    'sort_order' => $event->pivot->sort_order,
                ];
            }),
        ]);
    }
}
