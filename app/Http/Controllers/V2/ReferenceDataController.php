<?php

namespace App\Http\Controllers\V2;

use App\Data\TalentLanguages;
use App\Http\Controllers\Controller;
use App\Support\Iso3166CountryRepository;
use Illuminate\Http\JsonResponse;

class ReferenceDataController extends Controller
{
    /**
     * GET /api/v1/countries
     *
     * ISO 3166-1 alpha-2 countries for nationality pickers.
     */
    public function countries(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Iso3166CountryRepository::all(),
        ]);
    }

    /**
     * GET /api/v1/talent-languages
     *
     * Allowed spoken languages for talent profiles (pick-list).
     */
    public function languages(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => TalentLanguages::all(),
        ]);
    }
}
