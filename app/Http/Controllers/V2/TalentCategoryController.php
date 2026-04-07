<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\TalentCategoryResource;
use App\Services\V2\TalentCategoryService;
use Illuminate\Http\JsonResponse;

class TalentCategoryController extends Controller
{
    public function __construct(
        private readonly TalentCategoryService $talentCategoryService
    ) {}

    /**
     * GET /api/v1/categories-talents
     */
    public function index(): JsonResponse
    {
        $categories = $this->talentCategoryService->getAll();

        return response()->json([
            'success' => true,
            'data' => TalentCategoryResource::collection($categories),
        ]);
    }
}
