<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\VenueCategoryResource;
use App\Services\V2\VenueCategoryService;
use Illuminate\Http\JsonResponse;

class VenueCategoryController extends Controller
{
    public function __construct(
        private readonly VenueCategoryService $venueCategoryService
    ) {}

    /**
     * GET /api/v1/categories-venue | /api/v1/categories-venues
     */
    public function index(): JsonResponse
    {
        $categories = $this->venueCategoryService->getAll();

        return response()->json([
            'success' => true,
            'data' => VenueCategoryResource::collection($categories),
        ]);
    }
}
