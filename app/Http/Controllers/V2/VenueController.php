<?php

namespace App\Http\Controllers\V2;

use App\Helpers\MediaHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\StoreVenueRequest;
use App\Http\Requests\V2\UpdateProfilePublicationStatusRequest;
use App\Http\Requests\V2\UpdatePublishStatusRequest;
use App\Http\Requests\V2\UpdateVenueRequest;
use App\Http\Resources\V2\VenueResource;
use App\Models\VenueV2;
use App\Services\V2\VenueService;
use App\Support\ProfilePublicationStatus;
use App\Support\PublishStatus;
use App\Support\V2ProfileCoverImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    public function __construct(
        private readonly VenueService $venueService
    ) {}

    /**
     * GET /api/v2/my-venues
     */
    public function myVenues(Request $request): JsonResponse
    {
        $venues = $this->venueService->getUserVenues($request->user());

        return response()->json([
            'success' => true,
            'data' => VenueResource::collection($venues),
        ]);
    }

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
            'status' => $venue->status ?? ProfilePublicationStatus::DRAFT,
            'status_label' => ProfilePublicationStatus::labels()[$venue->status ?? ProfilePublicationStatus::DRAFT]
                ?? ($venue->status ?? ProfilePublicationStatus::DRAFT),
            'publish_status' => $venue->publish_status ?? PublishStatus::DRAFT,
            'publish_status_label' => PublishStatus::labels()[$venue->publish_status ?? PublishStatus::DRAFT]
                ?? ($venue->publish_status ?? PublishStatus::DRAFT),
            'event_type' => $venue->event_type ?? 'free',
            'category_id' => $venue->category_id,
            'subcategory_ids' => $venue->subcategory_ids ?? [],
            'address' => $venue->address,
            'latitude' => $venue->latitude !== null ? (float) $venue->latitude : null,
            'longitude' => $venue->longitude !== null ? (float) $venue->longitude : null,
            'description' => $venue->description ?? null,
            'description_items' => $venue->description_items ?? [],
            'allow_dogs' => (bool) ($venue->allow_dogs ?? false),
            'allowance_of_dogs' => $venue->allowance_of_dogs,
            'wheelchair_accessible' => (bool) ($venue->wheelchair_accessible ?? false),
            'accessibility_description' => $venue->accessibility_description,
            'parking' => (bool) ($venue->parking ?? false),
            'valet' => (bool) ($venue->valet ?? false),
            'play_area' => (bool) ($venue->play_area ?? false),
            'contact_phone' => $venue->contact_phone,
            'contact_email' => $venue->contact_email,
            'contact_website' => $venue->contact_website,
            'contact_box_message' => $venue->contact_box_message,
            'contact_box_design_message' => $venue->contact_box_design_message,
            'facebook_url' => $venue->facebook_url,
            'instagram_url' => $venue->instagram_url,
            'tiktok_url' => $venue->tiktok_url,
            'opening_hours' => $venue->opening_hours ?? [],
            'show_upcoming_events' => (bool) ($venue->show_upcoming_events ?? false),
            'show_past_events' => (bool) ($venue->show_past_events ?? false),
            'image_path' => V2ProfileCoverImage::effectiveStoredPathForProfile($venue, $venue->user),
            'profile_image' => V2ProfileCoverImage::coverImageUrl($venue, $venue->user),
            'image_url' => V2ProfileCoverImage::coverImageUrl($venue, $venue->user),
        ];

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
        $venue->load('user');

        $data = [
            'id' => $venue->id,
            'title' => $venue->title,
            'status' => $venue->status ?? ProfilePublicationStatus::DRAFT,
            'status_label' => ProfilePublicationStatus::labels()[$venue->status ?? ProfilePublicationStatus::DRAFT]
                ?? ($venue->status ?? ProfilePublicationStatus::DRAFT),
            'publish_status' => $venue->publish_status ?? PublishStatus::DRAFT,
            'publish_status_label' => PublishStatus::labels()[$venue->publish_status ?? PublishStatus::DRAFT]
                ?? ($venue->publish_status ?? PublishStatus::DRAFT),
            'event_type' => $venue->event_type ?? 'free',
            'category_id' => $venue->category_id,
            'subcategory_ids' => is_array($venue->subcategory_ids) ? $venue->subcategory_ids : [],
            'address' => $venue->address,
            'description' => $venue->description ?? null,
            'description_items' => $venue->description_items ?? [],
            'contact_box_message' => $venue->contact_box_message,
            'contact_box_design_message' => $venue->contact_box_design_message,
            'allowance_of_dogs' => $venue->allowance_of_dogs,
            'accessibility_description' => $venue->accessibility_description,
            'image_path' => V2ProfileCoverImage::effectiveStoredPathForProfile($venue, $venue->user),
            'profile_image' => V2ProfileCoverImage::coverImageUrl($venue, $venue->user),
            'image_url' => V2ProfileCoverImage::coverImageUrl($venue, $venue->user),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Venue updated successfully',
            'data' => $data,
        ]);
    }

    /**
     * PATCH /api/v2/venues/{id}/status
     */
    public function updateStatus(UpdateProfilePublicationStatusRequest $request, int $id): JsonResponse
    {
        $venue = VenueV2::findOrFail($id);

        $user = $request->user();
        if (!$user || ($venue->user_id !== $user->id && !$user->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $venue->update(['status' => $request->validated('status')]);
        $venue->refresh();
        $venue->load(['category', 'user']);

        return response()->json([
            'success' => true,
            'message' => 'Publication status updated successfully',
            'data' => new VenueResource($venue),
        ]);
    }

    /**
     * PATCH /api/v2/venues/{id}/publish-status
     */
    public function updatePublishStatus(UpdatePublishStatusRequest $request, int $id): JsonResponse
    {
        $venue = VenueV2::findOrFail($id);

        $user = $request->user();
        if (! $user || ($venue->user_id !== $user->id && ! $user->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $venue->update(['publish_status' => $request->validated('publish_status')]);
        $venue->refresh();
        $venue->load(['category', 'user']);

        return response()->json([
            'success' => true,
            'message' => 'Publish status updated successfully',
            'data' => new VenueResource($venue),
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
