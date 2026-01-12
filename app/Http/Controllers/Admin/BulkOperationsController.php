<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BulkOperationsController extends Controller
{
    /**
     * POST /api/admin/events/bulk/delete
     *
     * Bulk delete events.
     */
    public function deleteEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:events,id',
        ]);

        $count = Event::whereIn('id', $validated['ids'])->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'message' => "{$count} event(s) deleted successfully.",
                'affected' => $count,
            ],
        ]);
    }

    /**
     * POST /api/admin/events/bulk/publish
     *
     * Bulk publish events.
     */
    public function publishEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:events,id',
        ]);

        $count = Event::whereIn('id', $validated['ids'])
            ->where('is_cancelled', false)
            ->where('is_archived', false)
            ->update([
                'is_published' => true,
                'published_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => "{$count} event(s) published successfully.",
                'affected' => $count,
            ],
        ]);
    }

    /**
     * POST /api/admin/events/bulk/unpublish
     *
     * Bulk unpublish events.
     */
    public function unpublishEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:events,id',
        ]);

        $count = Event::whereIn('id', $validated['ids'])->update([
            'is_published' => false,
            'is_featured' => false,
            'published_at' => null,
            'featured_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => "{$count} event(s) unpublished successfully.",
                'affected' => $count,
            ],
        ]);
    }

    /**
     * POST /api/admin/events/bulk/archive
     *
     * Bulk archive events.
     */
    public function archiveEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:events,id',
        ]);

        $count = Event::whereIn('id', $validated['ids'])->update([
            'is_archived' => true,
            'is_published' => false,
            'is_featured' => false,
            'archived_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => "{$count} event(s) archived successfully.",
                'affected' => $count,
            ],
        ]);
    }

    /**
     * POST /api/admin/events/bulk/category
     *
     * Bulk update category for events.
     */
    public function updateCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:events,id',
            'category_id' => 'required|integer|exists:categories,id',
        ]);

        $count = Event::whereIn('id', $validated['ids'])->update([
            'category_id' => $validated['category_id'],
            'subcategory_id' => null, // Reset subcategory when changing category
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => "{$count} event(s) updated successfully.",
                'affected' => $count,
            ],
        ]);
    }

    /**
     * POST /api/admin/events/bulk/feature
     *
     * Bulk feature events.
     */
    public function featureEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:events,id',
        ]);

        $count = Event::whereIn('id', $validated['ids'])
            ->where('is_published', true)
            ->where('is_cancelled', false)
            ->where('is_archived', false)
            ->update([
                'is_featured' => true,
                'featured_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => "{$count} event(s) featured successfully.",
                'affected' => $count,
            ],
        ]);
    }

    /**
     * POST /api/admin/events/bulk/unfeature
     *
     * Bulk unfeature events.
     */
    public function unfeatureEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:events,id',
        ]);

        $count = Event::whereIn('id', $validated['ids'])->update([
            'is_featured' => false,
            'featured_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => "{$count} event(s) unfeatured successfully.",
                'affected' => $count,
            ],
        ]);
    }
}
