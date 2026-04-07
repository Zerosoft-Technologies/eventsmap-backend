<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\OrganiserCategoryResource;
use App\Services\V2\OrganiserCategoryService;
use Illuminate\Http\JsonResponse;

class OrganiserCategoryController extends Controller
{
    public function __construct(
        private readonly OrganiserCategoryService $organiserCategoryService
    ) {}

    /**
     * GET /api/v1/categories-organisers
     */
    public function index(): JsonResponse
    {
        $categories = $this->organiserCategoryService->getAll();

        return response()->json([
            'success' => true,
            'data' => OrganiserCategoryResource::collection($categories),
        ]);
    }
}
