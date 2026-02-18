<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventOrganizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizerEventFixedController extends Controller
{
    /**
     * GET /api/admin/organizer-fixed
     *
     * Fixed endpoint that returns real data from events_organizer table.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate input
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string|in:title,created_at,start_datetime,view_count',
            'order' => 'nullable|string|in:asc,desc',
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:draft,published,featured,cancelled,archived',
            'category' => 'nullable|string|max:100',
        ]);

        // Start with a simple query
        $query = EventOrganizer::query();

        // Apply filters
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%")
                  ->orWhere('city', 'ILIKE', "%{$search}%");
            });
        }

        if (!empty($validated['status'])) {
            $status = $validated['status'];
            switch ($status) {
                case 'published':
                    $query->where('is_published', true)
                          ->where('is_cancelled', false)
                          ->where('is_archived', false);
                    break;
                case 'featured':
                    $query->where('is_featured', true)
                          ->where('is_cancelled', false)
                          ->where('is_archived', false);
                    break;
                case 'cancelled':
                    $query->where('is_cancelled', true);
                    break;
                case 'archived':
                    $query->where('is_archived', true);
                    break;
                case 'draft':
                    $query->where('is_published', false)
                          ->where('is_cancelled', false)
                          ->where('is_archived', false);
                    break;
            }
        }

        if (!empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        // Apply sorting
        $sort = $validated['sort'] ?? 'created_at';
        $order = $validated['order'] ?? 'desc';
        $query->orderBy($sort, $order);

        // Get pagination
        $perPage = $validated['per_page'] ?? 20;
        $events = $query->paginate($perPage);

        // Transform data manually to avoid resource issues
        $transformedData = collect($events->items())->map(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'description' => $event->description,
                'short_description' => $event->short_description,
                'status' => $this->getStatus($event),
                'category' => $event->category,
                'price' => $event->price,
                'currency' => $event->currency,
                'start_datetime' => $event->start_datetime?->toIso8601String(),
                'end_datetime' => $event->end_datetime?->toIso8601String(),
                'city' => $event->city,
                'country' => $event->country,
                'is_published' => $event->is_published,
                'is_featured' => $event->is_featured,
                'view_count' => $event->view_count ?? 0,
                'created_at' => $event->created_at?->toIso8601String(),
                'updated_at' => $event->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformedData,
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
     * Determine the status of an event.
     */
    private function getStatus($event): string
    {
        if ($event->is_archived) {
            return 'archived';
        } elseif ($event->is_cancelled) {
            return 'cancelled';
        } elseif ($event->is_featured) {
            return 'featured';
        } elseif ($event->is_published) {
            return 'published';
        }
        return 'draft';
    }
}
