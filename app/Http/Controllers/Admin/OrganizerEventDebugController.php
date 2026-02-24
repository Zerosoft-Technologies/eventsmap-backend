<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventOrganizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizerEventDebugController extends Controller
{
    /**
     * GET /api/admin/organizer/debug
     *
     * Debug endpoint to test raw data fetching.
     */
    public function debug(Request $request): JsonResponse
    {
        // Simple query without any scopes or relationships
        $events = EventOrganizer::query()
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'count' => $events->count(),
            'data' => $events->toArray(),
            'table_name' => (new EventOrganizer())->getTable(),
        ]);
    }

    /**
     * GET /api/admin/organizer/debug-with-relations
     *
     * Debug endpoint to test with relationships.
     */
    public function debugWithRelations(Request $request): JsonResponse
    {
        // Query with relationships
        $events = EventOrganizer::query()
            ->with(['eventImages', 'talents'])
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'count' => $events->count(),
            'data' => $events->toArray(),
        ]);
    }
}
