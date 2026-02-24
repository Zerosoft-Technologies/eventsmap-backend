<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminEventOrganizerResource;
use App\Models\EventOrganizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizerEventShowController extends Controller
{
    /**
     * GET /api/admin/organizer/{id}
     *
     * Get specific organizer event details.
     * Returns null if not found.
     */
    public function show(int $id): JsonResponse
    {
        // First check if the record exists
        $exists = EventOrganizer::where('id', $id)->exists();
        
        if (!$exists) {
            return response()->json([
                'success' => false,
                'message' => 'Organizer event not found',
                'data' => null
            ], 404);
        }

        // Now fetch the event with relationships
        $event = EventOrganizer::query()
            ->with(['eventImages', 'talents'])
            ->withTrashed()
            ->find($id);

        // Double-check (shouldn't be necessary but for safety)
        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Organizer event not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new AdminEventOrganizerResource($event),
        ]);
    }

    /**
     * Alternative implementation that returns null without error message
     */
    public function showSilent(int $id): JsonResponse
    {
        $event = EventOrganizer::query()
            ->with(['eventImages', 'talents'])
            ->withTrashed()
            ->find($id);

        return response()->json([
            'success' => true,
            'data' => $event ? new AdminEventOrganizerResource($event) : null,
        ]);
    }

    /**
     * Implementation with try-catch for findOrFail
     */
    public function showWithException(int $id): JsonResponse
    {
        try {
            $event = EventOrganizer::query()
                ->with(['eventImages', 'talents'])
                ->withTrashed()
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new AdminEventOrganizerResource($event),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Organizer event not found',
                'data' => null
            ], 404);
        }
    }
}
