<?php

namespace App\Http\Controllers\V2;

use App\Helpers\MediaHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\StoreTalentRequest;
use App\Http\Requests\V2\UpdateProfilePublicationStatusRequest;
use App\Http\Requests\V2\UpdatePublishStatusRequest;
use App\Http\Requests\V2\UpdateTalentRequest;
use App\Http\Resources\V2\TalentResource;
use App\Models\EventInvitation;
use App\Models\TalentV2;
use App\Services\V2\EventInvitationService;
use App\Services\V2\TalentService;
use App\Support\ProfilePublicationStatus;
use App\Support\PublishStatus;
use App\Support\TalentDateOfBirth;
use App\Support\V2ProfileCoverImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TalentController extends Controller
{
    public function __construct(
        private readonly TalentService $talentService,
        private readonly EventInvitationService $eventInvitationService
    ) {}

    /**
     * GET /api/v2/my-talents
     */
    public function myTalents(Request $request): JsonResponse
    {
        $talents = $this->talentService->getUserTalents($request->user());

        return response()->json([
            'success' => true,
            'data' => TalentResource::collection($talents),
        ]);
    }

    /**
     * POST /api/v2/talents
     */
    public function store(StoreTalentRequest $request): JsonResponse
    {
        $talent = $this->talentService->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Talent created successfully',
            'data' => new TalentResource($talent),
        ], 201);
    }

    /**
     * GET /api/v2/talents/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $talent = TalentV2::with([
            'category', 'eventCategory', 'user', 'talentCategory', 'talentSubcategories',
        ])->findOrFail($id);

        $user = $request->user();
        if (!$talent->isOwner($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this talent.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Talent fetched successfully',
            'data' => $this->talentOwnerDetailPayload($talent),
        ]);
    }

    /**
     * PUT /api/v2/talents/{id}
     */
    public function update(UpdateTalentRequest $request, int $id): JsonResponse
    {
        $talent = TalentV2::findOrFail($id);

        $user = $request->user();
        if (!$user || $talent->user_id !== $user->id) {
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

        $talent = $this->talentService->update($talent, $data, $request);

        $talent->load([
            'category', 'eventCategory', 'user', 'talentCategory', 'talentSubcategories',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Talent updated successfully',
            'data' => $this->talentOwnerDetailPayload($talent),
        ]);
    }

    /**
     * Single shape for GET talent (owner/admin) and PUT update so the SPA does not lose fields (e.g. age) after PATCH.
     *
     * @return array<string, mixed>
     */
    private function talentOwnerDetailPayload(TalentV2 $talent): array
    {
        $talent->loadMissing([
            'category', 'eventCategory', 'user', 'talentCategory', 'talentSubcategories',
        ]);

        $data = [
            'id' => $talent->id,
            'title' => $talent->title,
            'slug' => $talent->slug,
            'status' => $talent->status ?? ProfilePublicationStatus::DRAFT,
            'status_label' => ProfilePublicationStatus::labels()[$talent->status ?? ProfilePublicationStatus::DRAFT]
                ?? ($talent->status ?? ProfilePublicationStatus::DRAFT),
            'publish_status' => $talent->publish_status ?? PublishStatus::DRAFT,
            'publish_status_label' => PublishStatus::labels()[$talent->publish_status ?? PublishStatus::DRAFT]
                ?? ($talent->publish_status ?? PublishStatus::DRAFT),
            'event_type' => $talent->event_type ?? 'free',
            'category_id' => $talent->category_id,
            'subcategory_ids' => is_array($talent->subcategory_ids) ? $talent->subcategory_ids : [],
            'talent_category_id' => $talent->talent_category_id,
            'talent_subcategory_ids' => $talent->talentSubcategories->pluck('id')->values(),
            'city' => $talent->city,
            'address' => $talent->address,
            'latitude' => $talent->latitude !== null ? (float) $talent->latitude : null,
            'longitude' => $talent->longitude !== null ? (float) $talent->longitude : null,
            'description' => $talent->description ?? null,
            'contact_phone' => $talent->contact_phone,
            'contact_email' => $talent->contact_email,
            'contact_website' => $talent->contact_website,
            'contact_box_message' => $talent->contact_box_message,
            'contact_box_design_message' => $talent->contact_box_design_message,
            'facebook_url' => $talent->facebook_url,
            'instagram_url' => $talent->instagram_url,
            'tiktok_url' => $talent->tiktok_url,
            'fan_club_url' => $talent->fan_club_url,
            'nationality' => $talent->nationality,
            'show_nationality' => $talent->show_nationality,
            'date_of_birth' => TalentDateOfBirth::toApiDate($talent->date_of_birth),
            'age' => TalentDateOfBirth::resolvedAge($talent->age, $talent->date_of_birth),
            'show_age' => $talent->show_age,
            'languages' => $talent->languages ?? [],
            'highlights' => $talent->highlights,
            'show_upcoming_events' => (bool) ($talent->show_upcoming_events ?? false),
            'show_past_events' => (bool) ($talent->show_past_events ?? false),
            'image_path' => V2ProfileCoverImage::effectiveStoredPathForProfile($talent, $talent->user),
            'profile_image' => V2ProfileCoverImage::coverImageUrl($talent, $talent->user),
            'image_url' => V2ProfileCoverImage::coverImageUrl($talent, $talent->user),
        ];

        $additionalImages = $talent->additional_images ?? [];
        $additionalImageUrls = [];

        if (! empty($additionalImages) && is_array($additionalImages)) {
            $galleryImages = \App\Models\GalleryImage::whereIn('image_id', $additionalImages)
                ->where('user_id', $talent->user_id)
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

        if ($talent->talent_category_id && $talent->relationLoaded('talentCategory') && $talent->talentCategory) {
            $data['talent_category'] = [
                'id' => $talent->talentCategory->id,
                'name' => $talent->talentCategory->name,
                'slug' => $talent->talentCategory->slug,
            ];
        }

        if ($talent->category_id && $talent->relationLoaded('eventCategory') && $talent->eventCategory) {
            $data['event_category'] = [
                'id' => $talent->eventCategory->id,
                'name' => $talent->eventCategory->name,
                'slug' => $talent->eventCategory->slug,
            ];
        }

        if ($talent->show_upcoming_events ?? false) {
            $data['upcoming_events'] = $this->eventInvitationService->upcomingAcceptedEventsPayloadForProfileUser(
                (int) $talent->user_id,
                EventInvitation::TYPE_TALENT
            );
        }

        return $data;
    }

    /**
     * PATCH /api/v2/talents/{id}/status
     */
    public function updateStatus(UpdateProfilePublicationStatusRequest $request, int $id): JsonResponse
    {
        $talent = TalentV2::findOrFail($id);

        $user = $request->user();
        if (!$user || ($talent->user_id !== $user->id && !$user->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $talent->update(['status' => $request->validated('status')]);
        $talent->refresh();
        $talent->load(['category', 'user', 'talentCategory', 'talentSubcategories']);

        $this->eventInvitationService->hydrateUpcomingAcceptedInvitationEventsOnProfiles([$talent], EventInvitation::TYPE_TALENT);

        return response()->json([
            'success' => true,
            'message' => 'Publication status updated successfully',
            'data' => new TalentResource($talent),
        ]);
    }

    /**
     * PATCH /api/v2/talents/{id}/publish-status
     */
    public function updatePublishStatus(UpdatePublishStatusRequest $request, int $id): JsonResponse
    {
        $talent = TalentV2::findOrFail($id);

        $user = $request->user();
        if (! $user || ($talent->user_id !== $user->id && ! $user->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $talent->update(['publish_status' => $request->validated('publish_status')]);
        $talent->refresh();
        $talent->load(['category', 'user', 'talentCategory', 'talentSubcategories']);

        $this->eventInvitationService->hydrateUpcomingAcceptedInvitationEventsOnProfiles([$talent], EventInvitation::TYPE_TALENT);

        return response()->json([
            'success' => true,
            'message' => 'Publish status updated successfully',
            'data' => new TalentResource($talent),
        ]);
    }

    /**
     * DELETE /api/v2/talents/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $talent = TalentV2::findOrFail($id);

        $user = request()->user();
        if (!$user || ($talent->user_id !== $user->id && !$user->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->talentService->delete($talent);

        return response()->json([
            'success' => true,
            'message' => 'Talent deleted successfully',
        ]);
    }
}
