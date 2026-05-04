<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VenueV2\StoreVenueV2Request;
use App\Http\Requests\Admin\VenueV2\UpdateVenueV2Request;
use App\Http\Resources\Admin\AdminVenueV2Resource;
use App\Models\VenueV2;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * AdminVenueV2Controller - Admin CRUD for V2 Venues.
 *
 * Provides full CRUD, approval, and bulk operations
 * for venue profiles. Requires admin authentication.
 */
class AdminVenueV2Controller extends Controller
{
    /**
     * GET /api/admin/venues-v2
     *
     * List all venues with search, filters, sorting, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'is_approved' => 'nullable|boolean',
            'category_id' => 'nullable|integer|exists:categories,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'sort' => 'nullable|string|in:created_at,title',
            'order' => 'nullable|string|in:asc,desc',
        ]);

        $query = VenueV2::query()
            ->with(['category', 'user']);

        // Search filter
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->input('search');
            $q->where(function ($query) use ($search) {
                $query->where('title', 'ILIKE', "%{$search}%")
                    ->orWhere('slug', 'ILIKE', "%{$search}%")
                    ->orWhere('address', 'ILIKE', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('email', 'ILIKE', "%{$search}%")
                            ->orWhere('name', 'ILIKE', "%{$search}%");
                    });
            });
        });

        // Approval filter
        $query->when($request->has('is_approved'), function ($q) use ($request) {
            $q->where('is_approved', $request->boolean('is_approved'));
        });

        // Category filter
        $query->when($request->filled('category_id'), function ($q) use ($request) {
            $q->where('category_id', $request->input('category_id'));
        });

        // User filter
        $query->when($request->filled('user_id'), function ($q) use ($request) {
            $q->where('user_id', $request->input('user_id'));
        });

        // Sorting
        $sort = $request->input('sort', 'created_at');
        $order = $request->input('order', 'desc');
        $query->orderBy($sort, $order);

        // Pagination
        $perPage = $request->input('per_page', 20);
        $items = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Venues fetched successfully',
            'data' => [
                'venues' => AdminVenueV2Resource::collection($items->items()),
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/admin/venues-v2/stats
     *
     * Get venue statistics for admin dashboard.
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total' => VenueV2::count(),
            'pending_approval' => VenueV2::where('is_approved', false)->count(),
            'approved' => VenueV2::where('is_approved', true)->count(),
            'trashed' => VenueV2::onlyTrashed()->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Venue stats fetched successfully',
            'data' => $stats,
        ]);
    }

    /**
     * GET /api/admin/venues-v2/{id}
     *
     * Show a single venue with all relationships.
     */
    public function show($id): JsonResponse
    {
        $venue = VenueV2::with(['category', 'user'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Venue fetched successfully',
            'data' => new AdminVenueV2Resource($venue),
        ]);
    }

    /**
     * POST /api/admin/venues-v2
     *
     * Create a new venue as admin.
     */
    public function store(StoreVenueV2Request $request): JsonResponse
    {
        $data = $request->validated();

        $venue = DB::transaction(function () use ($data, $request) {
            $venueData = [
                'user_id' => $data['user_id'],
                'title' => $data['title'],
                'slug' => $this->generateUniqueSlug($data['title']),
                'event_type' => $data['event_type'] ?? 'free',
                'category_id' => $data['category_id'] ?? null,
                'address' => $data['address'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'is_approved' => $data['is_approved'] ?? false,
            ];

            $optionalFields = [
                'subcategory_ids', 'description', 'description_items',
                'allow_dogs', 'allowance_of_dogs', 'wheelchair_accessible', 'accessibility_description',
                'parking', 'valet', 'play_area',
                'contact_phone', 'contact_email', 'contact_website',
                'contact_box_message', 'contact_box_design_message',
                'facebook_url', 'instagram_url', 'tiktok_url',
                'opening_hours', 'show_upcoming_events', 'show_past_events',
            ];

            foreach ($optionalFields as $field) {
                if (array_key_exists($field, $data)) {
                    $venueData[$field] = $data[$field];
                }
            }

            // Handle main image
            if (isset($data['image_path'])) {
                if ($data['image_path'] instanceof UploadedFile) {
                    $venueData['image_path'] = $this->storeImage($data['image_path']);
                } elseif (is_string($data['image_path'])) {
                    $venueData['image_path'] = $data['image_path'];
                }
            }

            // Handle additional images
            if (! empty($data['additional_images']) && is_array($data['additional_images'])) {
                $paths = [];
                foreach ($data['additional_images'] as $imageRef) {
                    if ($imageRef instanceof UploadedFile) {
                        $paths[] = $this->storeImage($imageRef);
                    } elseif (is_string($imageRef) && $imageRef !== '') {
                        $paths[] = $imageRef;
                    }
                }
                if (! empty($paths)) {
                    $venueData['additional_images'] = $paths;
                }
            }

            $venue = VenueV2::create($venueData);

            Log::info('Admin created venue', ['venue_id' => $venue->id, 'admin_id' => $request->user()->id]);

            return $venue->load(['category', 'user']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Venue created successfully',
            'data' => new AdminVenueV2Resource($venue),
        ], 201);
    }

    /**
     * PUT /api/admin/venues-v2/{id}
     *
     * Update an existing venue.
     */
    public function update(UpdateVenueV2Request $request, $id): JsonResponse
    {
        $venue = VenueV2::findOrFail($id);
        $data = $request->validated();

        $venue = DB::transaction(function () use ($venue, $data, $request) {
            $allowedFields = [
                'user_id', 'title', 'event_type', 'category_id', 'subcategory_ids',
                'address', 'latitude', 'longitude',
                'description', 'description_items',
                'allow_dogs', 'allowance_of_dogs', 'wheelchair_accessible', 'accessibility_description',
                'parking', 'valet', 'play_area',
                'contact_phone', 'contact_email', 'contact_website',
                'contact_box_message', 'contact_box_design_message',
                'facebook_url', 'instagram_url', 'tiktok_url',
                'opening_hours', 'show_upcoming_events', 'show_past_events', 'is_approved',
            ];

            $updateData = [];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            // Handle main image
            if ($request->hasFile('image_path')) {
                $this->deleteImage($venue->image_path);
                $updateData['image_path'] = $this->storeImage($request->file('image_path'));
            } elseif (isset($data['image_path']) && is_string($data['image_path'])) {
                $updateData['image_path'] = $data['image_path'];
            }

            // Handle additional images
            if ($request->hasFile('additional_images')) {
                $this->deleteAllAdditionalImages($venue);
                $newImages = [];
                foreach ($request->file('additional_images') as $image) {
                    $newImages[] = $this->storeImage($image);
                }
                $updateData['additional_images'] = $newImages;
            } elseif (array_key_exists('additional_images', $data) && is_array($data['additional_images'])) {
                $this->deleteAllAdditionalImages($venue);
                $updateData['additional_images'] = array_values(array_filter($data['additional_images'], fn ($v) => ! empty($v)));
            }

            $venue->update($updateData);

            Log::info('Admin updated venue', ['venue_id' => $venue->id, 'admin_id' => $request->user()->id]);

            return $venue->load(['category', 'user']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Venue updated successfully',
            'data' => new AdminVenueV2Resource($venue),
        ]);
    }

    /**
     * DELETE /api/admin/venues-v2/{id}
     *
     * Soft delete a venue.
     */
    public function destroy($id): JsonResponse
    {
        $venue = VenueV2::findOrFail($id);

        $venue->delete();
        Log::info('Admin soft-deleted venue', ['venue_id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Venue deleted successfully',
        ]);
    }

    /**
     * POST /api/admin/venues-v2/{id}/approve
     *
     * Approve a venue.
     */
    public function approve($id): JsonResponse
    {
        $venue = VenueV2::findOrFail($id);

        $venue->update(['is_approved' => true]);
        Log::info('Admin approved venue', ['venue_id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Venue approved successfully',
            'data' => new AdminVenueV2Resource($venue->load(['category', 'user'])),
        ]);
    }

    /**
     * POST /api/admin/venues-v2/{id}/unapprove
     *
     * Unapprove a venue.
     */
    public function unapprove($id): JsonResponse
    {
        $venue = VenueV2::findOrFail($id);

        $venue->update(['is_approved' => false]);
        Log::info('Admin unapproved venue', ['venue_id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Venue unapproved successfully',
            'data' => new AdminVenueV2Resource($venue->load(['category', 'user'])),
        ]);
    }

    /**
     * POST /api/admin/venues-v2/bulk-approve
     *
     * Approve multiple venues.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:venue_v2,id',
        ]);

        $count = VenueV2::whereIn('id', $request->input('ids'))
            ->update(['is_approved' => true]);

        Log::info('Admin bulk-approved venues', ['count' => $count, 'admin_id' => $request->user()->id]);

        return response()->json([
            'success' => true,
            'message' => "{$count} venues approved successfully",
        ]);
    }

    /**
     * POST /api/admin/venues-v2/bulk-delete
     *
     * Soft delete multiple venues.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:venue_v2,id',
        ]);

        $count = VenueV2::whereIn('id', $request->input('ids'))->count();
        VenueV2::whereIn('id', $request->input('ids'))->delete();

        Log::info('Admin bulk-deleted venues', ['count' => $count, 'admin_id' => $request->user()->id]);

        return response()->json([
            'success' => true,
            'message' => "{$count} venues deleted successfully",
        ]);
    }

    // ──────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────

    private function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        if (empty($slug)) {
            $slug = 'venue-'.Str::random(8);
        }
        $base = $slug;
        $counter = 1;
        while (VenueV2::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function storeImage(UploadedFile $image): string
    {
        $extension = $image->getClientOriginalExtension();
        $filename = Str::random(40).'.'.strtolower($extension);
        $path = $image->storeAs('venues', $filename, 'public');
        if (! $path) {
            throw new \Exception('Failed to store image file');
        }

        return $path;
    }

    private function deleteImage(?string $imagePath): void
    {
        if (! $imagePath) {
            return;
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $imagePath)) {
            return;
        }
        if (Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }
    }

    private function deleteAllAdditionalImages(VenueV2 $venue): void
    {
        if (! empty($venue->additional_images) && is_array($venue->additional_images)) {
            foreach ($venue->additional_images as $imagePath) {
                if (is_string($imagePath)) {
                    $this->deleteImage($imagePath);
                }
            }
        }
    }
}
