<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TalentV2\StoreTalentV2Request;
use App\Http\Requests\Admin\TalentV2\UpdateTalentV2Request;
use App\Http\Resources\Admin\AdminTalentV2Resource;
use App\Models\TalentV2;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * AdminTalentV2Controller - Admin CRUD for V2 Talents.
 *
 * Provides full CRUD, approval, and bulk operations
 * for talent profiles. Requires admin authentication.
 */
class AdminTalentV2Controller extends Controller
{
    /**
     * GET /api/admin/talents-v2
     *
     * List all talents with search, filters, sorting, and pagination.
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

        $query = TalentV2::query()
            ->with(['category', 'user', 'talentCategory', 'talentSubcategories']);

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
            'message' => 'Talents fetched successfully',
            'data' => [
                'talents' => AdminTalentV2Resource::collection($items->items()),
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
     * GET /api/admin/talents-v2/stats
     *
     * Get talent statistics for admin dashboard.
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total' => TalentV2::count(),
            'pending_approval' => TalentV2::where('is_approved', false)->count(),
            'approved' => TalentV2::where('is_approved', true)->count(),
            'trashed' => TalentV2::onlyTrashed()->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Talent stats fetched successfully',
            'data' => $stats,
        ]);
    }

    /**
     * GET /api/admin/talents-v2/{id}
     *
     * Show a single talent with all relationships.
     */
    public function show($id): JsonResponse
    {
        $talent = TalentV2::with(['category', 'user', 'talentCategory', 'talentSubcategories'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Talent fetched successfully',
            'data' => new AdminTalentV2Resource($talent),
        ]);
    }

    /**
     * POST /api/admin/talents-v2
     *
     * Create a new talent as admin.
     */
    public function store(StoreTalentV2Request $request): JsonResponse
    {
        $data = $request->validated();

        $talent = DB::transaction(function () use ($data, $request) {
            $talentData = [
                'user_id' => $data['user_id'],
                'title' => $data['title'],
                'slug' => $this->generateUniqueSlug($data['title']),
                'event_type' => $data['event_type'] ?? 'free',
                'category_id' => $data['category_id'] ?? null,
                'talent_category_id' => $data['talent_category_id'] ?? null,
                'address' => $data['address'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'is_approved' => $data['is_approved'] ?? false,
            ];

            $optionalFields = [
                'subcategory_ids', 'city', 'description',
                'contact_phone', 'contact_email', 'contact_website',
                'contact_box_message', 'contact_box_design_message',
                'facebook_url', 'instagram_url', 'tiktok_url', 'fan_club_url',
                'nationality', 'show_nationality', 'age', 'show_age',
                'languages', 'highlights',
                'show_upcoming_events', 'show_past_events',
            ];

            foreach ($optionalFields as $field) {
                if (array_key_exists($field, $data)) {
                    $talentData[$field] = $data[$field];
                }
            }

            // Handle main image
            if (isset($data['image_path'])) {
                if ($data['image_path'] instanceof UploadedFile) {
                    $talentData['image_path'] = $this->storeImage($data['image_path']);
                } elseif (is_string($data['image_path'])) {
                    $talentData['image_path'] = $data['image_path'];
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
                    $talentData['additional_images'] = $paths;
                }
            }

            $talent = TalentV2::create($talentData);

            if (! empty($data['talent_subcategory_ids'])) {
                $talent->talentSubcategories()->sync($data['talent_subcategory_ids']);
            }

            Log::info('Admin created talent', ['talent_id' => $talent->id, 'admin_id' => $request->user()->id]);

            return $talent->load(['category', 'user', 'talentCategory', 'talentSubcategories']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Talent created successfully',
            'data' => new AdminTalentV2Resource($talent),
        ], 201);
    }

    /**
     * PUT /api/admin/talents-v2/{id}
     *
     * Update an existing talent.
     */
    public function update(UpdateTalentV2Request $request, $id): JsonResponse
    {
        $talent = TalentV2::findOrFail($id);
        $data = $request->validated();

        $talent = DB::transaction(function () use ($talent, $data, $request) {
            $allowedFields = [
                'user_id', 'title', 'event_type', 'category_id', 'subcategory_ids',
                'talent_category_id', 'city', 'address', 'latitude', 'longitude',
                'description', 'contact_phone', 'contact_email', 'contact_website',
                'contact_box_message', 'contact_box_design_message',
                'facebook_url', 'instagram_url', 'tiktok_url', 'fan_club_url',
                'nationality', 'show_nationality', 'age', 'show_age',
                'languages', 'highlights',
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
                $this->deleteImage($talent->image_path);
                $updateData['image_path'] = $this->storeImage($request->file('image_path'));
            } elseif (isset($data['image_path']) && is_string($data['image_path'])) {
                $updateData['image_path'] = $data['image_path'];
            }

            // Handle additional images
            if ($request->hasFile('additional_images')) {
                $this->deleteAllAdditionalImages($talent);
                $newImages = [];
                foreach ($request->file('additional_images') as $image) {
                    $newImages[] = $this->storeImage($image);
                }
                $updateData['additional_images'] = $newImages;
            } elseif (array_key_exists('additional_images', $data) && is_array($data['additional_images'])) {
                $this->deleteAllAdditionalImages($talent);
                $updateData['additional_images'] = array_values(array_filter($data['additional_images'], fn ($v) => ! empty($v)));
            }

            $talent->update($updateData);

            if (array_key_exists('talent_subcategory_ids', $data)) {
                $talent->talentSubcategories()->sync($data['talent_subcategory_ids'] ?? []);
            }

            Log::info('Admin updated talent', ['talent_id' => $talent->id, 'admin_id' => $request->user()->id]);

            return $talent->load(['category', 'user', 'talentCategory', 'talentSubcategories']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Talent updated successfully',
            'data' => new AdminTalentV2Resource($talent),
        ]);
    }

    /**
     * DELETE /api/admin/talents-v2/{id}
     *
     * Soft delete a talent.
     */
    public function destroy($id): JsonResponse
    {
        $talent = TalentV2::findOrFail($id);

        $talent->delete();
        Log::info('Admin soft-deleted talent', ['talent_id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Talent deleted successfully',
        ]);
    }

    /**
     * POST /api/admin/talents-v2/{id}/approve
     *
     * Approve a talent.
     */
    public function approve($id): JsonResponse
    {
        $talent = TalentV2::findOrFail($id);

        $talent->update(['is_approved' => true]);
        Log::info('Admin approved talent', ['talent_id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Talent approved successfully',
            'data' => new AdminTalentV2Resource($talent->load(['category', 'user'])),
        ]);
    }

    /**
     * POST /api/admin/talents-v2/{id}/unapprove
     *
     * Unapprove a talent.
     */
    public function unapprove($id): JsonResponse
    {
        $talent = TalentV2::findOrFail($id);

        $talent->update(['is_approved' => false]);
        Log::info('Admin unapproved talent', ['talent_id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Talent unapproved successfully',
            'data' => new AdminTalentV2Resource($talent->load(['category', 'user'])),
        ]);
    }

    /**
     * POST /api/admin/talents-v2/bulk-approve
     *
     * Approve multiple talents.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:talents_v2,id',
        ]);

        $count = TalentV2::whereIn('id', $request->input('ids'))
            ->update(['is_approved' => true]);

        Log::info('Admin bulk-approved talents', ['count' => $count, 'admin_id' => $request->user()->id]);

        return response()->json([
            'success' => true,
            'message' => "{$count} talents approved successfully",
        ]);
    }

    /**
     * POST /api/admin/talents-v2/bulk-delete
     *
     * Soft delete multiple talents.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:talents_v2,id',
        ]);

        $count = TalentV2::whereIn('id', $request->input('ids'))->count();
        TalentV2::whereIn('id', $request->input('ids'))->delete();

        Log::info('Admin bulk-deleted talents', ['count' => $count, 'admin_id' => $request->user()->id]);

        return response()->json([
            'success' => true,
            'message' => "{$count} talents deleted successfully",
        ]);
    }

    // ──────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────

    private function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        if (empty($slug)) {
            $slug = 'talent-'.Str::random(8);
        }
        $base = $slug;
        $counter = 1;
        while (TalentV2::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function storeImage(UploadedFile $image): string
    {
        $extension = $image->getClientOriginalExtension();
        $filename = Str::random(40).'.'.strtolower($extension);
        $path = $image->storeAs('talents', $filename, 'public');
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

    private function deleteAllAdditionalImages(TalentV2 $talent): void
    {
        if (! empty($talent->additional_images) && is_array($talent->additional_images)) {
            foreach ($talent->additional_images as $imagePath) {
                if (is_string($imagePath)) {
                    $this->deleteImage($imagePath);
                }
            }
        }
    }
}
