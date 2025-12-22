<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * GET /api/categories
     *
     * Fetch all categories with their subcategories.
     * Optimized for frontend dropdowns and filters.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->with('subcategories:id,category_id,name,slug')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * GET /api/categories/{slug}/subcategories
     *
     * Fetch subcategories for a given category by slug.
     * Returns 404 if category is not found.
     *
     * @param string $slug
     * @return JsonResponse
     */
    public function subcategories(string $slug): JsonResponse
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $subcategories = SubCategory::query()
            ->where('category_id', $category->id)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'success' => true,
            'data' => [
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug
                ],
                'subcategories' => $subcategories
            ]
        ]);
    }

    /**
     * GET /api/categories/{slug}
     *
     * Fetch a single category by slug with its subcategories.
     *
     * @param string $slug
     * @return JsonResponse
     */
    public function show(string $slug): JsonResponse
    {
        $category = Category::query()
            ->with('subcategories:id,category_id,name,slug')
            ->where('slug', $slug)
            ->first(['id', 'name', 'slug']);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }
}
