<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * GET /api/admin/categories
     *
     * List categories with optional tree or flat format.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'format' => 'nullable|string|in:tree,flat',
            'include_inactive' => 'nullable|boolean',
        ]);

        $query = Category::query()->orderBy('display_order')->orderBy('name');

        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        $categories = $query->with(['subcategories' => function ($q) use ($request) {
            $q->orderBy('display_order')->orderBy('name');
            if (!$request->boolean('include_inactive')) {
                $q->where('is_active', true);
            }
        }])->get();

        $format = $request->input('format', 'tree');

        if ($format === 'flat') {
            $flatList = [];
            foreach ($categories as $category) {
                $flatList[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'icon' => $category->icon,
                    'color' => $category->color,
                    'display_order' => $category->display_order,
                    'is_active' => $category->is_active,
                    'is_featured' => $category->is_featured,
                    'parent_id' => null,
                    'type' => 'category',
                ];
                foreach ($category->subcategories as $subcategory) {
                    $flatList[] = [
                        'id' => $subcategory->id,
                        'name' => $subcategory->name,
                        'slug' => $subcategory->slug,
                        'description' => $subcategory->description,
                        'display_order' => $subcategory->display_order,
                        'is_active' => $subcategory->is_active,
                        'parent_id' => $category->id,
                        'type' => 'subcategory',
                    ];
                }
            }
            return response()->json([
                'success' => true,
                'data' => $flatList,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'icon' => $category->icon,
                    'color' => $category->color,
                    'display_order' => $category->display_order,
                    'is_active' => $category->is_active,
                    'is_featured' => $category->is_featured,
                    'subcategories' => $category->subcategories->map(function ($sub) {
                        return [
                            'id' => $sub->id,
                            'name' => $sub->name,
                            'slug' => $sub->slug,
                            'description' => $sub->description,
                            'display_order' => $sub->display_order,
                            'is_active' => $sub->is_active,
                        ];
                    }),
                ];
            }),
        ]);
    }

    /**
     * GET /api/admin/categories/{id}
     *
     * Get single category with subcategories.
     */
    public function show(int $id): JsonResponse
    {
        $category = Category::with('subcategories')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'icon' => $category->icon,
                'color' => $category->color,
                'display_order' => $category->display_order,
                'is_active' => $category->is_active,
                'is_featured' => $category->is_featured,
                'events_count' => $category->events()->count(),
                'subcategories' => $category->subcategories->map(function ($sub) {
                    return [
                        'id' => $sub->id,
                        'name' => $sub->name,
                        'slug' => $sub->slug,
                        'description' => $sub->description,
                        'display_order' => $sub->display_order,
                        'is_active' => $sub->is_active,
                    ];
                }),
                'created_at' => $category->created_at?->toIso8601String(),
                'updated_at' => $category->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/admin/categories
     *
     * Create a new category or subcategory.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'slug' => 'nullable|string|regex:/^[a-z0-9-]+$/',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'parent_id' => 'nullable|integer|exists:categories,id',
        ]);

        // If parent_id is provided, create subcategory
        if (!empty($validated['parent_id'])) {
            return $this->createSubcategory($validated);
        }

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (Category::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        // Set defaults
        $validated['display_order'] = $validated['display_order'] ?? (Category::max('display_order') + 1);
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['is_featured'] = $validated['is_featured'] ?? false;

        unset($validated['parent_id']);
        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'icon' => $category->icon,
                'color' => $category->color,
                'display_order' => $category->display_order,
                'is_active' => $category->is_active,
                'is_featured' => $category->is_featured,
                'created_at' => $category->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Create a subcategory.
     */
    protected function createSubcategory(array $validated): JsonResponse
    {
        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (SubCategory::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        $subcategory = SubCategory::create([
            'category_id' => $validated['parent_id'],
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'display_order' => $validated['display_order'] ?? (SubCategory::where('category_id', $validated['parent_id'])->max('display_order') + 1),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $subcategory->id,
                'name' => $subcategory->name,
                'slug' => $subcategory->slug,
                'description' => $subcategory->description,
                'display_order' => $subcategory->display_order,
                'is_active' => $subcategory->is_active,
                'parent_id' => $subcategory->category_id,
                'type' => 'subcategory',
                'created_at' => $subcategory->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * PUT /api/admin/categories/{id}
     *
     * Update a category.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'slug' => 'nullable|string|regex:/^[a-z0-9-]+$/|unique:categories,slug,' . $id,
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'icon' => $category->icon,
                'color' => $category->color,
                'display_order' => $category->display_order,
                'is_active' => $category->is_active,
                'is_featured' => $category->is_featured,
                'updated_at' => $category->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * DELETE /api/admin/categories/{id}
     *
     * Delete a category.
     */
    public function destroy(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        // Check for subcategories
        if ($category->subcategories()->count() > 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DEPENDENCY_ERROR',
                    'message' => 'Cannot delete category. It has subcategories. Delete subcategories first.',
                ],
            ], 409);
        }

        // Check for events
        $eventsCount = \App\Models\Event::where('category_id', $id)->count();
        if ($eventsCount > 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DEPENDENCY_ERROR',
                    'message' => "Cannot delete category. It has {$eventsCount} event(s) attached.",
                ],
            ], 409);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Category deleted successfully.',
            ],
        ]);
    }

    /**
     * POST /api/admin/categories/{id}/activate
     *
     * Activate a category.
     */
    public function activate(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Category activated successfully.',
            ],
        ]);
    }

    /**
     * POST /api/admin/categories/{id}/deactivate
     *
     * Deactivate a category.
     */
    public function deactivate(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        // Warn about events
        $eventsCount = \App\Models\Event::where('category_id', $id)->where('is_published', true)->count();

        $category->update(['is_active' => false]);

        $message = 'Category deactivated successfully.';
        if ($eventsCount > 0) {
            $message .= " Warning: This category has {$eventsCount} published event(s).";
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => $message,
            ],
        ]);
    }

    /**
     * PATCH /api/admin/categories/reorder
     *
     * Bulk reorder categories.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:categories,id',
            'items.*.display_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            Category::where('id', $item['id'])->update([
                'display_order' => $item['display_order'],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Categories reordered successfully.',
            ],
        ]);
    }

    /**
     * GET /api/admin/categories/{id}/subcategories
     *
     * Get subcategories for a category.
     */
    public function getSubcategories(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $subcategories = $category->subcategories()->orderBy('display_order')->get();

        return response()->json([
            'success' => true,
            'data' => $subcategories->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'name' => $sub->name,
                    'slug' => $sub->slug,
                    'description' => $sub->description,
                    'display_order' => $sub->display_order,
                    'is_active' => $sub->is_active,
                ];
            }),
        ]);
    }

    /**
     * PUT /api/admin/subcategories/{id}
     *
     * Update a subcategory.
     */
    public function updateSubcategory(Request $request, int $id): JsonResponse
    {
        $subcategory = SubCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'slug' => 'nullable|string|regex:/^[a-z0-9-]+$/|unique:subcategories,slug,' . $id,
            'description' => 'nullable|string|max:500',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $subcategory->update($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $subcategory->id,
                'name' => $subcategory->name,
                'slug' => $subcategory->slug,
                'description' => $subcategory->description,
                'display_order' => $subcategory->display_order,
                'is_active' => $subcategory->is_active,
                'updated_at' => $subcategory->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * DELETE /api/admin/subcategories/{id}
     *
     * Delete a subcategory.
     */
    public function destroySubcategory(int $id): JsonResponse
    {
        $subcategory = SubCategory::findOrFail($id);

        // Check for events
        $eventsCount = \App\Models\Event::where('subcategory_id', $id)->count();
        if ($eventsCount > 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DEPENDENCY_ERROR',
                    'message' => "Cannot delete subcategory. It has {$eventsCount} event(s) attached.",
                ],
            ], 409);
        }

        $subcategory->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Subcategory deleted successfully.',
            ],
        ]);
    }
}
