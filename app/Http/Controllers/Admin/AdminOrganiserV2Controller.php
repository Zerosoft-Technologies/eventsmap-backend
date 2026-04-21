<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrganiserV2\StoreOrganiserV2Request;
use App\Http\Requests\Admin\OrganiserV2\UpdateOrganiserV2Request;
use App\Http\Resources\Admin\AdminOrganiserV2Resource;
use App\Models\OrganiserV2;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * AdminOrganiserV2Controller - Admin CRUD for V2 Organisers.
 *
 * Provides full CRUD, approval, and bulk operations
 * for organiser profiles. Requires admin authentication.
 */
class AdminOrganiserV2Controller extends Controller
{
    /**
     * GET /api/admin/organisers-v2
     *
     * List all organisers with search, filters, sorting, and pagination.
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

        $query = OrganiserV2::query()
            ->with(['category', 'user', 'organiserCategory', 'organiserSubcategories']);

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
            'message' => 'Organisers fetched successfully',
            'data' => [
                'organisers' => AdminOrganiserV2Resource::collection($items->items()),
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
     * GET /api/admin/organisers-v2/stats
     *
     * Get organiser statistics for admin dashboard.
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total' => OrganiserV2::count(),
            'pending_approval' => OrganiserV2::where('is_approved', false)->count(),
            'approved' => OrganiserV2::where('is_approved', true)->count(),
            'trashed' => OrganiserV2::onlyTrashed()->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Organiser stats fetched successfully',
            'data' => $stats,
        ]);
    }

    /**
     * GET /api/admin/organisers-v2/{id}
     *
     * Show a single organiser with all relationships.
     */
    public function show($id): JsonResponse
    {
        $organiser = OrganiserV2::with(['category', 'user', 'organiserCategory', 'organiserSubcategories'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Organiser fetched successfully',
            'data' => new AdminOrganiserV2Resource($organiser),
        ]);
    }

    /**
     * POST /api/admin/organisers-v2
     *
     * Create a new organiser as admin.
     */
    public function store(StoreOrganiserV2Request $request): JsonResponse
    {
        $data = $request->validated();

        $organiser = DB::transaction(function () use ($data, $request) {
            $organiserData = [
                'user_id' => $data['user_id'],
                'title' => $data['title'],
                'slug' => $this->generateUniqueSlug($data['title']),
                'event_type' => $data['event_type'] ?? 'free',
                'category_id' => $data['category_id'] ?? null,
                'organiser_category_id' => $data['organiser_category_id'] ?? null,
                'address' => $data['address'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'is_approved' => $data['is_approved'] ?? false,
            ];

            $optionalFields = [
                'subcategory_ids', 'description',
                'contact_phone', 'contact_email', 'contact_website',
                'facebook_url', 'instagram_url', 'tiktok_url',
                'show_upcoming_events', 'show_past_events',
            ];

            foreach ($optionalFields as $field) {
                if (array_key_exists($field, $data)) {
                    $organiserData[$field] = $data[$field];
                }
            }

            // Handle main image
            if (isset($data['image_path'])) {
                if ($data['image_path'] instanceof UploadedFile) {
                    $organiserData['image_path'] = $this->storeImage($data['image_path']);
                } elseif (is_string($data['image_path'])) {
                    $organiserData['image_path'] = $data['image_path'];
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
                    $organiserData['additional_images'] = $paths;
                }
            }

            $organiser = OrganiserV2::create($organiserData);

            if (! empty($data['organiser_subcategory_ids'])) {
                $organiser->organiserSubcategories()->sync($data['organiser_subcategory_ids']);
            }

            Log::info('Admin created organiser', ['organiser_id' => $organiser->id, 'admin_id' => $request->user()->id]);

            return $organiser->load(['category', 'user', 'organiserCategory', 'organiserSubcategories']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Organiser created successfully',
            'data' => new AdminOrganiserV2Resource($organiser),
        ], 201);
    }

    /**
     * PUT /api/admin/organisers-v2/{id}
     *
     * Update an existing organiser.
     */
    public function update(UpdateOrganiserV2Request $request, $id): JsonResponse
    {
        $organiser = OrganiserV2::findOrFail($id);
        $data = $request->validated();

        $organiser = DB::transaction(function () use ($organiser, $data, $request) {
            $allowedFields = [
                'user_id', 'title', 'event_type', 'category_id', 'subcategory_ids',
                'organiser_category_id', 'address', 'latitude', 'longitude',
                'description', 'contact_phone', 'contact_email', 'contact_website',
                'facebook_url', 'instagram_url', 'tiktok_url',
                'show_upcoming_events', 'show_past_events', 'is_approved',
            ];

            $updateData = [];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            // Handle main image
            if ($request->hasFile('image_path')) {
                $this->deleteImage($organiser->image_path);
                $updateData['image_path'] = $this->storeImage($request->file('image_path'));
            } elseif (isset($data['image_path']) && is_string($data['image_path'])) {
                $updateData['image_path'] = $data['image_path'];
            }

            // Handle additional images
            if ($request->hasFile('additional_images')) {
                $this->deleteAllAdditionalImages($organiser);
                $newImages = [];
                foreach ($request->file('additional_images') as $image) {
                    $newImages[] = $this->storeImage($image);
                }
                $updateData['additional_images'] = $newImages;
            } elseif (array_key_exists('additional_images', $data) && is_array($data['additional_images'])) {
                $this->deleteAllAdditionalImages($organiser);
                $updateData['additional_images'] = array_values(array_filter($data['additional_images'], fn ($v) => ! empty($v)));
            }

            $organiser->update($updateData);

            if (array_key_exists('organiser_subcategory_ids', $data)) {
                $organiser->organiserSubcategories()->sync($data['organiser_subcategory_ids'] ?? []);
            }

            Log::info('Admin updated organiser', ['organiser_id' => $organiser->id, 'admin_id' => $request->user()->id]);

            return $organiser->load(['category', 'user', 'organiserCategory', 'organiserSubcategories']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Organiser updated successfully',
            'data' => new AdminOrganiserV2Resource($organiser),
        ]);
    }

    /**
     * DELETE /api/admin/organisers-v2/{id}
     *
     * Soft delete an organiser.
     */
    public function destroy($id): JsonResponse
    {
        $organiser = OrganiserV2::findOrFail($id);

        $organiser->delete();
        Log::info('Admin soft-deleted organiser', ['organiser_id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Organiser deleted successfully',
        ]);
    }

    /**
     * POST /api/admin/organisers-v2/{id}/approve
     *
     * Approve an organiser.
     */
    public function approve($id): JsonResponse
    {
        $organiser = OrganiserV2::findOrFail($id);

        $organiser->update(['is_approved' => true]);
        Log::info('Admin approved organiser', ['organiser_id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Organiser approved successfully',
            'data' => new AdminOrganiserV2Resource($organiser->load(['category', 'user'])),
        ]);
    }

    /**
     * POST /api/admin/organisers-v2/{id}/unapprove
     *
     * Unapprove an organiser.
     */
    public function unapprove($id): JsonResponse
    {
        $organiser = OrganiserV2::findOrFail($id);

        $organiser->update(['is_approved' => false]);
        Log::info('Admin unapproved organiser', ['organiser_id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Organiser unapproved successfully',
            'data' => new AdminOrganiserV2Resource($organiser->load(['category', 'user'])),
        ]);
    }

    /**
     * POST /api/admin/organisers-v2/bulk-approve
     *
     * Approve multiple organisers.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:organiser_v2,id',
        ]);

        $count = OrganiserV2::whereIn('id', $request->input('ids'))
            ->update(['is_approved' => true]);

        Log::info('Admin bulk-approved organisers', ['count' => $count, 'admin_id' => $request->user()->id]);

        return response()->json([
            'success' => true,
            'message' => "{$count} organisers approved successfully",
        ]);
    }

    /**
     * POST /api/admin/organisers-v2/bulk-delete
     *
     * Soft delete multiple organisers.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:organiser_v2,id',
        ]);

        $count = OrganiserV2::whereIn('id', $request->input('ids'))->count();
        OrganiserV2::whereIn('id', $request->input('ids'))->delete();

        Log::info('Admin bulk-deleted organisers', ['count' => $count, 'admin_id' => $request->user()->id]);

        return response()->json([
            'success' => true,
            'message' => "{$count} organisers deleted successfully",
        ]);
    }

    // ──────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────

    private function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        if (empty($slug)) {
            $slug = 'organiser-'.Str::random(8);
        }
        $base = $slug;
        $counter = 1;
        while (OrganiserV2::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function storeImage(UploadedFile $image): string
    {
        $extension = $image->getClientOriginalExtension();
        $filename = Str::random(40).'.'.strtolower($extension);
        $path = $image->storeAs('organisers', $filename, 'public');
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

    private function deleteAllAdditionalImages(OrganiserV2 $organiser): void
    {
        if (! empty($organiser->additional_images) && is_array($organiser->additional_images)) {
            foreach ($organiser->additional_images as $imagePath) {
                if (is_string($imagePath)) {
                    $this->deleteImage($imagePath);
                }
            }
        }
    }
}
