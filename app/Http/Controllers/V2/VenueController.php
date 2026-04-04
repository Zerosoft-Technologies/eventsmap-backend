<?php

namespace App\Http\Controllers\V2;

use App\Helpers\MediaHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\StoreVenueRequest;
use App\Http\Requests\V2\UpdateVenueRequest;
use App\Http\Resources\V2\VenueResource;
use App\Models\VenueV2;
use App\Services\V2\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    public function __construct(
        private readonly VenueService $venueService
    ) {}

    /**
     * POST /api/v2/venues
     */
    public function store(StoreVenueRequest $request): JsonResponse
    {
        $venue = $this->venueService->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Venue created successfully',
            'data' => new VenueResource($venue),
        ], 201);
    }

    /**
     * GET /api/v2/venues/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $venue = VenueV2::with(['category', 'user'])->findOrFail($id);

        $user = $request->user();
        if (!$venue->isOwner($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this venue.',
            ], 403);
        }

        $data = [
            'id' => $venue->id,
            'title' => $venue->title,
            'slug' => $venue->slug,
            'event_type' => $venue->event_type ?? 'free',
            'category_id' => $venue->category_id,
            'subcategory_ids' => $venue->subcategory_ids ?? [],
            'address' => $venue->address,
            'latitude' => $venue->latitude !== null ? (float) $venue->latitude : null,
            'longitude' => $venue->longitude !== null ? (float) $venue->longitude : null,
            'description' => $venue->description ?? null,
            'allow_dogs' => (bool) ($venue->allow_dogs ?? false),
            'wheelchair_accessible' => (bool) ($venue->wheelchair_accessible ?? false),
            'parking' => (bool) ($venue->parking ?? false),
            'valet' => (bool) ($venue->valet ?? false),
            'play_area' => (bool) ($venue->play_area ?? false),
            'contact_phone' => $venue->contact_phone,
            'contact_email' => $venue->contact_email,
            'contact_website' => $venue->contact_website,
            'facebook_url' => $venue->facebook_url,
            'instagram_url' => $venue->instagram_url,
            'tiktok_url' => $venue->tiktok_url,
            'opening_hours' => $venue->opening_hours ?? [],
            'show_upcoming_events' => (bool) ($venue->show_upcoming_events ?? false),
            'show_past_events' => (bool) ($venue->show_past_events ?? false),
            'image_path' => $venue->image_path,
        ];

        // Handle main image URL
        if ($venue->image_path) {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $venue->image_path)) {
                $galleryImage = \App\Models\GalleryImage::where('image_id', $venue->image_path)
                    ->where('user_id', $venue->user_id)
                    ->where('is_deleted', false)
                    ->first();

                $data['image_url'] = $galleryImage ? MediaHelper::url($galleryImage->file_path) : null;
            } else {
                $data['image_url'] = MediaHelper::url($venue->image_path);
            }
        } else {
            $data['image_url'] = null;
        }

        // Handle additional images
        $additionalImages = $venue->additional_images ?? [];
        $additionalImageUrls = [];

        if (!empty($additionalImages) && is_array($additionalImages)) {
            $galleryImages = \App\Models\GalleryImage::whereIn('image_id', $additionalImages)
                ->where('user_id', $venue->user_id)
                ->where('is_deleted', false)
                ->get()
                ->keyBy('image_id');

            foreach ($additionalImages as $imageId) {
                if (isset($galleryImages[$imageId])) {
                    $additionalImageUrls[] = MediaHelper::url($galleryImages[$imageId]->file_path);
                }
            }
        }

        $data['additional_images'] = $additionalImages;
        $data['additional_image_urls'] = $additionalImageUrls;

        return response()->json([
            'success' => true,
            'message' => 'Venue fetched successfully',
            'data' => $data,
        ]);
    }

    /**
     * PUT /api/v2/venues/{id}
     */
    public function update(UpdateVenueRequest $request, int $id): JsonResponse
    {
        $venue = VenueV2::findOrFail($id);

        $user = $request->user();
        if (!$user || $venue->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $data = $request->validated();

        // When frontend sends empty additional_images (cleared), validated() strips it.
        // Explicitly pass empty array so the service knows to clear them.
        if (!$request->hasFile('additional_images') && !array_key_exists('additional_images', $data)) {
            $data['additional_images'] = [];
        }

        $venue = $this->venueService->update($venue, $data, $request);

        $venue->refresh();

        $data = [
            'id' => $venue->id,
            'title' => $venue->title,
            'event_type' => $venue->event_type ?? 'free',
            'category_id' => $venue->category_id,
            'subcategory_ids' => is_array($venue->subcategory_ids) ? $venue->subcategory_ids : [],
            'address' => $venue->address,
            'description' => $venue->description ?? null,
            'image_url' => $venue->image_path ? MediaHelper::url($venue->image_path) : null,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Venue updated successfully',
            'data' => $data,
        ]);
    }

    /**
     * DELETE /api/v2/venues/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $venue = VenueV2::findOrFail($id);

        $user = request()->user();
        if (!$user || ($venue->user_id !== $user->id && !$user->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->venueService->delete($venue);

        return response()->json([
            'success' => true,
            'message' => 'Venue deleted successfully',
        ]);
    }
}
