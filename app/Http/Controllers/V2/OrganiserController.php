<?php

namespace App\Http\Controllers\V2;

use App\Helpers\MediaHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\StoreOrganiserRequest;
use App\Http\Requests\V2\UpdateOrganiserRequest;
use App\Http\Requests\V2\UpdateProfilePublicationStatusRequest;
use App\Http\Requests\V2\UpdatePublishStatusRequest;
use App\Http\Resources\V2\OrganiserResource;
use App\Http\Resources\V2\OrganiserSidebarResource;
use App\Models\EventInvitation;
use App\Models\OrganiserV2;
use App\Services\V2\EventInvitationService;
use App\Services\V2\OrganiserService;
use App\Support\ProfilePublicationStatus;
use App\Support\PublishStatus;
use App\Support\V2ProfileCoverImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganiserController extends Controller
{
    public function __construct(
        private readonly OrganiserService $organiserService,
        private readonly EventInvitationService $eventInvitationService
    ) {}

    /**
     * GET /api/v2/my-organisers
     *
     * Get all organisers for the authenticated user (for sidebar).
     */
    public function myOrganisers(Request $request): JsonResponse
    {
        $organisers = OrganiserV2::where('user_id', $request->user()->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => OrganiserSidebarResource::collection($organisers),
        ]);
    }

    /**
     * POST /api/v2/organisers
     *
     * Create a new organiser.
     */
    public function store(StoreOrganiserRequest $request): JsonResponse
    {
        $organiser = $this->organiserService->create($request->validated(), $request->user());
        $organiser->load(['category', 'user', 'organiserCategory', 'organiserSubcategories']);
        $this->eventInvitationService->hydrateUpcomingAcceptedInvitationEventsOnProfiles([$organiser], EventInvitation::TYPE_ORGANISER);
        $this->eventInvitationService->hydratePastAcceptedInvitationEventsOnProfiles([$organiser], EventInvitation::TYPE_ORGANISER);

        return response()->json([
            'success' => true,
            'message' => 'Organiser created successfully',
            'data' => new OrganiserResource($organiser),
        ], 201);
    }

    /**
     * GET /api/v2/organisers/{id}
     *
     * Full organiser details for edit form. Owner or admin only.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $organiser = OrganiserV2::with(['category', 'user', 'organiserCategory', 'organiserSubcategories'])->findOrFail($id);

        $user = $request->user();
        if (!$organiser->isOwner($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this organiser.',
            ], 403);
        }

        $subcategoryIds = $organiser->subcategory_ids ?? [];

        $data = [
            'id' => $organiser->id,
            'title' => $organiser->title,
            'slug' => $organiser->slug,
            'status' => $organiser->status ?? ProfilePublicationStatus::DRAFT,
            'status_label' => ProfilePublicationStatus::labels()[$organiser->status ?? ProfilePublicationStatus::DRAFT]
                ?? ($organiser->status ?? ProfilePublicationStatus::DRAFT),
            'publish_status' => $organiser->publish_status ?? PublishStatus::DRAFT,
            'publish_status_label' => PublishStatus::labels()[$organiser->publish_status ?? PublishStatus::DRAFT]
                ?? ($organiser->publish_status ?? PublishStatus::DRAFT),
            'event_type' => $organiser->event_type ?? 'free',
            'category_id' => $organiser->category_id,
            'subcategory_ids' => $subcategoryIds,
            'organiser_category_id' => $organiser->organiser_category_id,
            'organiser_subcategory_ids' => $organiser->organiserSubcategories->pluck('id')->values(),
            'address' => $organiser->address,
            'latitude' => $organiser->latitude !== null ? (float) $organiser->latitude : null,
            'longitude' => $organiser->longitude !== null ? (float) $organiser->longitude : null,
            'description' => $organiser->description ?? null,
            'contact_phone' => $organiser->contact_phone,
            'contact_email' => $organiser->contact_email,
            'contact_website' => $organiser->contact_website,
            'contact_box_message' => $organiser->contact_box_message,
            'contact_box_design_message' => $organiser->contact_box_design_message,
            'facebook_url' => $organiser->facebook_url,
            'instagram_url' => $organiser->instagram_url,
            'tiktok_url' => $organiser->tiktok_url,
            'show_upcoming_events' => (bool) ($organiser->show_upcoming_events ?? false),
            'show_past_events' => (bool) ($organiser->show_past_events ?? false),
            'image_path' => V2ProfileCoverImage::effectiveStoredPathForProfile($organiser, $organiser->user),
            'profile_image' => V2ProfileCoverImage::coverImageUrl($organiser, $organiser->user),
            'image_url' => V2ProfileCoverImage::coverImageUrl($organiser, $organiser->user),
        ];

        // Handle additional images
        $additionalImages = $organiser->additional_images ?? [];
        $additionalImageUrls = [];

        if (!empty($additionalImages) && is_array($additionalImages)) {
            $galleryImages = \App\Models\GalleryImage::whereIn('image_id', $additionalImages)
                ->where('user_id', $organiser->user_id)
                ->where('is_deleted', false)
                ->get()
                ->keyBy('image_id');

            foreach ($additionalImages as $imageId) {
                if (isset($galleryImages[$imageId])) {
                    $galleryImage = $galleryImages[$imageId];
                    $additionalImageUrls[] = MediaHelper::url($galleryImage->file_path);
                }
            }
        }

        $data['additional_images'] = $additionalImages;
        $data['additional_image_urls'] = $additionalImageUrls;

        if ($organiser->show_upcoming_events ?? false) {
            $data['upcoming_events'] = $this->eventInvitationService->upcomingAcceptedEventsPayloadForProfileUser(
                (int) $organiser->user_id,
                EventInvitation::TYPE_ORGANISER
            );
        }

        if ($organiser->show_past_events ?? false) {
            $data['past_events'] = $this->eventInvitationService->pastAcceptedEventsPayloadForProfileUser(
                (int) $organiser->user_id,
                EventInvitation::TYPE_ORGANISER
            );
        }

        $data['show_contact_box'] = (bool) ($organiser->show_contact_box ?? false);

        return response()->json([
            'success' => true,
            'message' => 'Organiser fetched successfully',
            'data' => $data,
        ]);
    }

    /**
     * PUT /api/v2/organisers/{id}
     *
     * Update an organiser. Only owner or admin can update.
     */
    public function update(UpdateOrganiserRequest $request, int $id): JsonResponse
    {
        $organiser = OrganiserV2::findOrFail($id);

        $user = $request->user();
        if (!$user || $organiser->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // $organiser = $this->organiserService->update($organiser, $request->validated(), $request);
        $data = $request->validated();
 
        // When frontend sends empty additional_images (cleared), validated() strips it.
        // Explicitly pass empty array so the service knows to clear them.
        if (!$request->hasFile('additional_images') && !array_key_exists('additional_images', $data)) {
            $data['additional_images'] = [];
        }
 
        $organiser = $this->organiserService->update($organiser, $data, $request);

        $organiser->refresh();
        $organiser->load(['organiserCategory', 'organiserSubcategories', 'user']);
        $subcategoryIds = is_array($organiser->subcategory_ids) ? $organiser->subcategory_ids : [];

        $data = [
            'id' => $organiser->id,
            'title' => $organiser->title,
            'status' => $organiser->status ?? ProfilePublicationStatus::DRAFT,
            'status_label' => ProfilePublicationStatus::labels()[$organiser->status ?? ProfilePublicationStatus::DRAFT]
                ?? ($organiser->status ?? ProfilePublicationStatus::DRAFT),
            'publish_status' => $organiser->publish_status ?? PublishStatus::DRAFT,
            'publish_status_label' => PublishStatus::labels()[$organiser->publish_status ?? PublishStatus::DRAFT]
                ?? ($organiser->publish_status ?? PublishStatus::DRAFT),
            'event_type' => $organiser->event_type ?? 'free',
            'category_id' => $organiser->category_id,
            'subcategory_ids' => $subcategoryIds,
            'organiser_category_id' => $organiser->organiser_category_id,
            'organiser_subcategory_ids' => $organiser->organiserSubcategories->pluck('id')->values(),
            'address' => $organiser->address,
            'description' => $organiser->description ?? null,
            'contact_box_message' => $organiser->contact_box_message,
            'contact_box_design_message' => $organiser->contact_box_design_message,
            'image_path' => V2ProfileCoverImage::effectiveStoredPathForProfile($organiser, $organiser->user),
            'profile_image' => V2ProfileCoverImage::coverImageUrl($organiser, $organiser->user),
            'image_url' => V2ProfileCoverImage::coverImageUrl($organiser, $organiser->user),
        ];

        if ($organiser->show_upcoming_events ?? false) {
            $data['upcoming_events'] = $this->eventInvitationService->upcomingAcceptedEventsPayloadForProfileUser(
                (int) $organiser->user_id,
                EventInvitation::TYPE_ORGANISER
            );
        }

        if ($organiser->show_past_events ?? false) {
            $data['past_events'] = $this->eventInvitationService->pastAcceptedEventsPayloadForProfileUser(
                (int) $organiser->user_id,
                EventInvitation::TYPE_ORGANISER
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Organiser updated successfully',
            'data' => $data,
        ]);
    }

    /**
     * PATCH /api/v2/organisers/{id}/status
     */
    public function updateStatus(UpdateProfilePublicationStatusRequest $request, int $id): JsonResponse
    {
        $organiser = OrganiserV2::findOrFail($id);

        $user = $request->user();
        if (!$user || ($organiser->user_id !== $user->id && !$user->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $organiser->update(['status' => $request->validated('status')]);
        $organiser->refresh();
        $organiser->load(['category', 'user', 'organiserCategory', 'organiserSubcategories']);

        $this->eventInvitationService->hydrateUpcomingAcceptedInvitationEventsOnProfiles([$organiser], EventInvitation::TYPE_ORGANISER);
        $this->eventInvitationService->hydratePastAcceptedInvitationEventsOnProfiles([$organiser], EventInvitation::TYPE_ORGANISER);

        return response()->json([
            'success' => true,
            'message' => 'Publication status updated successfully',
            'data' => new OrganiserResource($organiser),
        ]);
    }

    /**
     * PATCH /api/v2/organisers/{id}/publish-status
     */
    public function updatePublishStatus(UpdatePublishStatusRequest $request, int $id): JsonResponse
    {
        $organiser = OrganiserV2::findOrFail($id);

        $user = $request->user();
        if (! $user || ($organiser->user_id !== $user->id && ! $user->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $organiser->update(['publish_status' => $request->validated('publish_status')]);
        $organiser->refresh();
        $organiser->load(['category', 'user', 'organiserCategory', 'organiserSubcategories']);

        $this->eventInvitationService->hydrateUpcomingAcceptedInvitationEventsOnProfiles([$organiser], EventInvitation::TYPE_ORGANISER);
        $this->eventInvitationService->hydratePastAcceptedInvitationEventsOnProfiles([$organiser], EventInvitation::TYPE_ORGANISER);

        return response()->json([
            'success' => true,
            'message' => 'Publish status updated successfully',
            'data' => new OrganiserResource($organiser),
        ]);
    }

    /**
     * DELETE /api/v2/organisers/{id}
     *
     * Delete an organiser (soft delete). Only owner or admin can delete.
     */
    public function destroy(int $id): JsonResponse
    {
        $organiser = OrganiserV2::findOrFail($id);

        $user = request()->user();
        if (!$user || ($organiser->user_id !== $user->id && !$user->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->organiserService->delete($organiser);

        return response()->json([
            'success' => true,
            'message' => 'Organiser deleted successfully',
        ]);
    }
}
