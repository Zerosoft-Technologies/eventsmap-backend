<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\EventResource;
use App\Models\EventV2;
use App\Models\Wishlist;
use App\Services\V2\EventInvitedEntitiesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * WishlistController - Manages user's favorite events.
 *
 * Provides toggle (add/remove) and listing of wishlisted events.
 */
class WishlistController extends Controller
{
    public function __construct(
        private readonly EventInvitedEntitiesService $eventInvitedEntitiesService
    ) {}

    /**
     * POST /api/v2/events/{id}/wishlist
     *
     * Toggle wishlist status for an event.
     * If already wishlisted → remove. If not → add.
     */
    public function toggle(Request $request, int $id): JsonResponse
    {
        $event = EventV2::findOrFail($id);
        $user = $request->user();

        $existing = Wishlist::where('user_id', $user->id)
            ->where('event_v2_id', $event->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json([
                'success' => true,
                'message' => 'Removed from wishlist',
                'data' => [
                    'is_wishlisted' => false,
                ],
            ]);
        }

        Wishlist::create([
            'user_id' => $user->id,
            'event_v2_id' => $event->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Added to wishlist',
            'data' => [
                'is_wishlisted' => true,
            ],
        ]);
    }

    /**
     * GET /api/v2/my-wishlist
     *
     * Get all wishlisted events for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $eventIds = Wishlist::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->pluck('event_v2_id');

        $events = EventV2::whereIn('id', $eventIds)
            ->with(['category', 'venue'])
            ->get()
            ->sortBy(function ($event) use ($eventIds) {
                return $eventIds->search($event->id);
            })
            ->values();
        
        // Manually load subcategories for each event
        $events->each(function ($event) {
            $event->setRelation('subcategories', $event->subcategories_from_ids);
        });

        $this->eventInvitedEntitiesService->hydrate($events);

        return response()->json([
            'success' => true,
            'message' => 'Wishlist fetched successfully',
            'data' => EventResource::collection($events),
        ]);
    }
}
