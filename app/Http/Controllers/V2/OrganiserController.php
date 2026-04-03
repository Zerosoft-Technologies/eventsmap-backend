<?php

namespace App\Http\Controllers\V2;

use App\Helpers\MediaHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\StoreOrganiserRequest;
use App\Http\Requests\V2\UpdateOrganiserRequest;
use App\Http\Resources\V2\OrganiserResource;
use App\Models\OrganiserV2;
use App\Services\V2\OrganiserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganiserController extends Controller
{
    public function __construct(
        private readonly OrganiserService $organiserService
    ) {}

    /**
     * POST /api/v2/organisers
     *
     * Create a new organiser.
     */
    public function store(StoreOrganiserRequest $request): JsonResponse
    {
        $organiser = $this->organiserService->create($request->validated(), $request->user());

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
        $organiser = OrganiserV2::with(['category', 'user'])->findOrFail($id);

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
            'event_type' => $organiser->event_type ?? 'free',
            'category_id' => $organiser->category_id,
            'subcategory_ids' => $subcategoryIds,
            'address' => $organiser->address,
            'latitude' => $organiser->latitude !== null ? (float) $organiser->latitude : null,
            'longitude' => $organiser->longitude !== null ? (float) $organiser->longitude : null,
            'description' => $organiser->description ?? null,
            'contact_phone' => $organiser->contact_phone,
            'contact_email' => $organiser->contact_email,
            'contact_website' => $organiser->contact_website,
            'facebook_url' => $organiser->facebook_url,
            'instagram_url' => $organiser->instagram_url,
            'tiktok_url' => $organiser->tiktok_url,
            'show_upcoming_events' => (bool) ($organiser->show_upcoming_events ?? false),
            'show_past_events' => (bool) ($organiser->show_past_events ?? false),
            'image_path' => $organiser->image_path,
        ];

        // Handle main image URL
        if ($organiser->image_path) {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $organiser->image_path)) {
                $galleryImage = \App\Models\GalleryImage::where('image_id', $organiser->image_path)
                    ->where('user_id', $organiser->user_id)
                    ->where('is_deleted', false)
                    ->first();

                $data['image_url'] = $galleryImage ? MediaHelper::url($galleryImage->file_path) : null;
            } else {
                $data['image_url'] = MediaHelper::url($organiser->image_path);
            }
        } else {
            $data['image_url'] = null;
        }

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

        $organiser = $this->organiserService->update($organiser, $request->validated(), $request);

        $organiser->refresh();
        $subcategoryIds = is_array($organiser->subcategory_ids) ? $organiser->subcategory_ids : [];

        $data = [
            'id' => $organiser->id,
            'title' => $organiser->title,
            'event_type' => $organiser->event_type ?? 'free',
            'category_id' => $organiser->category_id,
            'subcategory_ids' => $subcategoryIds,
            'address' => $organiser->address,
            'description' => $organiser->description ?? null,
            'image_url' => $organiser->image_path ? MediaHelper::url($organiser->image_path) : null,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Organiser updated successfully',
            'data' => $data,
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
