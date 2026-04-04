<?php

namespace App\Services\V2;

use App\Models\OrganiserV2;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrganiserService
{
    // ──────────────────────────────────────
    // CRUD
    // ──────────────────────────────────────

    /**
     * Create a new organiser.
     */
    public function create(array $data, User $user): OrganiserV2
    {
        return DB::transaction(function () use ($data, $user) {
            $organiserData = [
                'user_id' => $user->id,
                'title' => $data['title'],
                'slug' => $this->generateUniqueSlug($data['title']),
                'event_type' => $data['event_type'] ?? 'free',
                'category_id' => $data['category_id'] ?? null,
                'address' => $data['address'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'is_approved' => false,
            ];

            $optionalFields = [
                'subcategory_ids', 'description',
                'contact_phone', 'contact_email', 'contact_website',
                'facebook_url', 'instagram_url', 'tiktok_url',
                'show_upcoming_events', 'show_past_events',
            ];

            foreach ($optionalFields as $field) {
                if (array_key_exists($field, $data)) {
                    $organiserData[$field] = $data[$field];
                }
            }

            // Handle main image
            if (isset($data['image_path'])) {
                if ($data['image_path'] instanceof UploadedFile) {
                    if ($data['image_path']->getError() !== UPLOAD_ERR_OK) {
                        throw new \Exception('File upload failed: ' . $data['image_path']->getErrorMessage());
                    }
                    $organiserData['image_path'] = $this->storeImage($data['image_path']);
                } elseif (is_string($data['image_path'])) {
                    $organiserData['image_path'] = $data['image_path'];
                }
            }

            // Handle additional images
            if (!empty($data['additional_images']) && is_array($data['additional_images'])) {
                $paths = [];
                foreach ($data['additional_images'] as $imageReference) {
                    if ($imageReference instanceof UploadedFile) {
                        $paths[] = $this->storeImage($imageReference);
                    } elseif (is_string($imageReference) && $imageReference !== '') {
                        $paths[] = $imageReference;
                    }
                }
                if (!empty($paths)) {
                    $organiserData['additional_images'] = $paths;
                }
            }

            $organiser = OrganiserV2::create($organiserData);

            Log::info('Organiser created', ['organiser_id' => $organiser->id, 'user_id' => $user->id]);

            return $organiser->load(['category', 'user']);
        });
    }

    /**
     * Update an existing organiser.
     */
    public function update(OrganiserV2 $organiser, array $data, ?Request $request = null): OrganiserV2
    {
        return DB::transaction(function () use ($organiser, $data, $request) {
            $allowedFields = [
                'title', 'event_type', 'category_id', 'subcategory_ids',
                'address', 'latitude', 'longitude',
                'description', 'contact_phone', 'contact_email', 'contact_website',
                'facebook_url', 'instagram_url', 'tiktok_url',
                'show_upcoming_events', 'show_past_events',
            ];

            $updateData = [];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            // Handle main image
            if ($request && $request->hasFile('image_path')) {
                // Delete old main image if exists
                if (!empty($organiser->image_path)) {
                    $this->deleteImage($organiser->image_path);
                }

                $file = $request->file('image_path');
                if ($file->getError() !== UPLOAD_ERR_OK) {
                    throw new \Exception('File upload failed: ' . $file->getErrorMessage());
                }
                $updateData['image_path'] = $this->storeImage($file);
            } elseif (isset($data['image_path']) && is_string($data['image_path'])) {
                $updateData['image_path'] = $data['image_path'];
            }

            // Handle additional images
            if ($request && $request->hasFile('additional_images')) {
                $this->deleteAllAdditionalImages($organiser);

                $newAdditionalImages = [];
                foreach ($request->file('additional_images') as $image) {
                    if ($image->getError() !== UPLOAD_ERR_OK) {
                        Log::error('Additional image upload error', [
                            'error_code' => $image->getError(),
                            'error_message' => $image->getErrorMessage()
                        ]);
                        continue;
                    }
                    $newAdditionalImages[] = $this->storeImage($image);
                }
                $updateData['additional_images'] = $newAdditionalImages;
            } elseif (array_key_exists('additional_images', $data)) {
                if (is_array($data['additional_images']) && !empty($data['additional_images'])) {
                    $filteredImages = array_filter($data['additional_images'], function ($image) {
                        return !is_null($image) && $image !== '';
                    });

                    if (!empty($filteredImages)) {
                        $this->deleteAllAdditionalImages($organiser);
                        $updateData['additional_images'] = array_values($filteredImages);
                    } else {
                        $this->deleteAllAdditionalImages($organiser);
                        $updateData['additional_images'] = null;
                    }
                } else {
                    // Empty array or null — clear all additional images
                    $this->deleteAllAdditionalImages($organiser);
                    $updateData['additional_images'] = null;
                }
            } elseif (isset($data['remove_additional_images']) && $data['remove_additional_images'] === true) {
                $this->deleteAllAdditionalImages($organiser);
                $updateData['additional_images'] = null;
            }

            Log::info('Organiser update data', ['updateData_keys' => array_keys($updateData), 'has_additional_images' => array_key_exists('additional_images', $updateData)]);

            $organiser->update($updateData);

            Log::info('Organiser updated', ['organiser_id' => $organiser->id]);

            return $organiser->load(['category', 'user']);
        });
    }

    /**
     * Soft delete an organiser.
     */
    public function delete(OrganiserV2 $organiser): void
    {
        DB::transaction(function () use ($organiser) {
            $organiser->delete();
            Log::info('Organiser soft deleted', ['organiser_id' => $organiser->id]);
        });
    }

    // ──────────────────────────────────────
    // Image Helpers
    // ──────────────────────────────────────

    private function storeImage(UploadedFile $image): string
    {
        $extension = $image->getClientOriginalExtension();
        $hash = Str::random(40);
        $filename = $hash . '.' . strtolower($extension);

        $path = $image->storeAs('organisers', $filename, 'public');

        if (!$path) {
            throw new \Exception('Failed to store image file');
        }

        return $path;
    }

    private function deleteImage(?string $imagePath): void
    {
        if (!$imagePath) {
            return;
        }

        // Don't delete UUID references from storage
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $imagePath)) {
            return;
        }

        if (Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }
    }

    private function deleteAllAdditionalImages(OrganiserV2 $organiser): void
    {
        if (!empty($organiser->additional_images) && is_array($organiser->additional_images)) {
            foreach ($organiser->additional_images as $imagePath) {
                if (is_string($imagePath)) {
                    $this->deleteImage($imagePath);
                }
            }
        }
    }

    // ──────────────────────────────────────
    // Slug Helper
    // ──────────────────────────────────────

    private function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (OrganiserV2::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
