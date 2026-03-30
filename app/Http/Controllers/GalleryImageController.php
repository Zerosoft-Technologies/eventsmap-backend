<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadGalleryImageRequest;
use App\Models\GalleryImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GalleryImageController extends Controller
{
    /**
     * GET /api/gallery-images
     *
     * Fetch paginated gallery images for the authenticated premium user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated',
            ], 401);
        }

        if (!$user->isPremiumAccount()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only premium users can access gallery',
            ], 403);
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 50));

        $images = GalleryImage::forUser($user->id)
            ->notDeleted()
            ->orderByLatest()
            ->paginate($perPage);

        $baseUrl = config('app.url');

        $formattedImages = $images->getCollection()->map(function ($image) use ($baseUrl) {
            return [
                'id'             => $image->id,
                'image_id'       => $image->image_id,
                'file_name'      => $image->file_name,
                'file_path'      => $image->file_path,
                'file_type'      => $image->file_type,
                'file_size'      => $image->file_size,
                'image_alt_text' => $image->image_alt_text,
                'upload_date'    => $image->upload_date?->toIso8601String(),
                'image_url'      => $baseUrl . '/storage/' . $image->file_path,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'images' => $formattedImages,
                'pagination' => [
                    'total'        => $images->total(),
                    'per_page'     => $images->perPage(),
                    'current_page' => $images->currentPage(),
                    'last_page'    => $images->lastPage(),
                    'from'         => $images->firstItem(),
                    'to'           => $images->lastItem(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/gallery-images/upload
     *
     * Upload a new gallery image for the authenticated premium user.
     */
    public function store(UploadGalleryImageRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated',
            ], 401);
        }

        if (!$user->isPremiumAccount()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only premium users can upload images',
            ], 403);
        }

        try {
            $file = $request->file('image');
            $imageId = (string) Str::uuid();
            $extension = $file->getClientOriginalExtension();
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            $directory = "gallery/user_{$user->id}";
            $storedFileName = "{$imageId}.{$extension}";

            Storage::disk('public')->putFileAs($directory, $file, $storedFileName);

            $filePath = "{$directory}/{$storedFileName}";

            $galleryImage = GalleryImage::create([
                'image_id'       => $imageId,
                'user_id'        => $user->id,
                'event_id'       => $request->input('event_id'),
                'file_name'      => $fileName,
                'file_size'      => $fileSize,
                'file_path'      => $filePath,
                'file_type'      => $mimeType,
                'image_alt_text' => $request->input('alt_text'),
            ]);

            $baseUrl = config('app.url');

            Log::info('Gallery image uploaded', [
                'user_id'  => $user->id,
                'image_id' => $imageId,
                'ip'       => $request->ip(),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Image uploaded successfully',
                'data'    => [
                    'id'          => $galleryImage->id,
                    'image_id'    => $galleryImage->image_id,
                    'file_name'   => $galleryImage->file_name,
                    'file_size'   => $galleryImage->file_size,
                    'image_url'   => $baseUrl . '/storage/' . $galleryImage->file_path,
                    'upload_date' => $galleryImage->upload_date?->toIso8601String(),
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to upload gallery image', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to upload image',
            ], 500);
        }
    }

    /**
     * DELETE /api/gallery-images/{image_id}
     *
     * Soft-delete a gallery image by its UUID image_id.
     */
    public function destroy(Request $request, string $image_id): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated',
            ], 401);
        }

        if (!$user->isPremiumAccount()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only premium users can access gallery',
            ], 403);
        }

        $image = GalleryImage::where('image_id', $image_id)
            ->notDeleted()
            ->first();

        if (!$image) {
            return response()->json([
                'status' => 'error',
                'message' => 'Image not found',
            ], 404);
        }

        if ($image->user_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized - you can only delete your own images',
            ], 403);
        }

        try {
            $image->update(['is_deleted' => true]);

            Log::info('Gallery image soft-deleted', [
                'user_id'  => $user->id,
                'image_id' => $image_id,
                'ip'       => $request->ip(),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Image deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete gallery image', [
                'user_id'  => $user->id,
                'image_id' => $image_id,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete image',
            ], 500);
        }
    }
}
