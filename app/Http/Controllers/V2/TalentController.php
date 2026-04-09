<?php

namespace App\Http\Controllers\V2;

use App\Helpers\MediaHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\StoreTalentRequest;
use App\Http\Requests\V2\UpdateTalentRequest;
use App\Http\Resources\V2\TalentResource;
use App\Models\TalentV2;
use App\Services\V2\TalentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TalentController extends Controller
{
    public function __construct(
        private readonly TalentService $talentService
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
        $talent = TalentV2::with(['category', 'user', 'talentCategory', 'talentSubcategories'])->findOrFail($id);

        $user = $request->user();
        if (!$talent->isOwner($user) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this talent.',
            ], 403);
        }

        $data = [
            'id' => $talent->id,
            'title' => $talent->title,
            'slug' => $talent->slug,
            'event_type' => $talent->event_type ?? 'free',
            'category_id' => $talent->category_id,
            'subcategory_ids' => $talent->subcategory_ids ?? [],
            'talent_category_id' => $talent->talent_category_id,
            'talent_subcategory_ids' => $talent->talentSubcategories->pluck('id')->values(),
            'address' => $talent->address,
            'latitude' => $talent->latitude !== null ? (float) $talent->latitude : null,
            'longitude' => $talent->longitude !== null ? (float) $talent->longitude : null,
            'description' => $talent->description ?? null,
            'contact_phone' => $talent->contact_phone,
            'contact_email' => $talent->contact_email,
            'contact_website' => $talent->contact_website,
            'facebook_url' => $talent->facebook_url,
            'instagram_url' => $talent->instagram_url,
            'tiktok_url' => $talent->tiktok_url,
            'fan_club_url' => $talent->fan_club_url,
            'nationality' => $talent->nationality,
            'show_nationality' => $talent->show_nationality,
            'age' => $talent->age,
            'show_age' => $talent->show_age,
            'languages' => $talent->languages ?? [],
            'highlights' => $talent->highlights,
            'show_upcoming_events' => (bool) ($talent->show_upcoming_events ?? false),
            'show_past_events' => (bool) ($talent->show_past_events ?? false),
            'image_path' => $talent->image_path,
        ];

        // Handle main image URL
        if ($talent->image_path) {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $talent->image_path)) {
                $galleryImage = \App\Models\GalleryImage::where('image_id', $talent->image_path)
                    ->where('user_id', $talent->user_id)
                    ->where('is_deleted', false)
                    ->first();

                $data['image_url'] = $galleryImage ? MediaHelper::url($galleryImage->file_path) : null;
            } else {
                $data['image_url'] = MediaHelper::url($talent->image_path);
            }
        } else {
            $data['image_url'] = null;
        }

        // Handle additional images
        $additionalImages = $talent->additional_images ?? [];
        $additionalImageUrls = [];

        if (!empty($additionalImages) && is_array($additionalImages)) {
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

        return response()->json([
            'success' => true,
            'message' => 'Talent fetched successfully',
            'data' => $data,
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

        $talent->refresh();
        $talent->load(['talentCategory', 'talentSubcategories']);

        $data = [
            'id' => $talent->id,
            'title' => $talent->title,
            'event_type' => $talent->event_type ?? 'free',
            'category_id' => $talent->category_id,
            'subcategory_ids' => is_array($talent->subcategory_ids) ? $talent->subcategory_ids : [],
            'talent_category_id' => $talent->talent_category_id,
            'talent_subcategory_ids' => $talent->talentSubcategories->pluck('id')->values(),
            'address' => $talent->address,
            'description' => $talent->description ?? null,
            'image_url' => $talent->image_path ? MediaHelper::url($talent->image_path) : null,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Talent updated successfully',
            'data' => $data,
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
