<?php

namespace App\Services\V2;

use App\Models\VenueV2;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VenueService
{
    public function create(array $data, User $user): VenueV2
    {
        return DB::transaction(function () use ($data, $user) {
            $venueData = [
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
                'allow_dogs', 'wheelchair_accessible', 'parking', 'valet', 'play_area',
                'contact_phone', 'contact_email', 'contact_website',
                'facebook_url', 'instagram_url', 'tiktok_url',
                'opening_hours',
                'show_upcoming_events', 'show_past_events',
            ];

            foreach ($optionalFields as $field) {
                if (array_key_exists($field, $data)) {
                    $venueData[$field] = $data[$field];
                }
            }

            // Handle main image
            if (isset($data['image_path'])) {
                if ($data['image_path'] instanceof UploadedFile) {
                    if ($data['image_path']->getError() !== UPLOAD_ERR_OK) {
                        throw new \Exception('File upload failed: ' . $data['image_path']->getErrorMessage());
                    }
                    $venueData['image_path'] = $this->storeImage($data['image_path']);
                } elseif (is_string($data['image_path'])) {
                    $venueData['image_path'] = $data['image_path'];
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
                    $venueData['additional_images'] = $paths;
                }
            }

            $venue = VenueV2::create($venueData);

            Log::info('Venue created', ['venue_id' => $venue->id, 'user_id' => $user->id]);

            return $venue->load(['category', 'user']);
        });
    }

    public function update(VenueV2 $venue, array $data, ?Request $request = null): VenueV2
    {
        return DB::transaction(function () use ($venue, $data, $request) {
            $allowedFields = [
                'title', 'event_type', 'category_id', 'subcategory_ids',
                'address', 'latitude', 'longitude',
                'description',
                'allow_dogs', 'wheelchair_accessible', 'parking', 'valet', 'play_area',
                'contact_phone', 'contact_email', 'contact_website',
                'facebook_url', 'instagram_url', 'tiktok_url',
                'opening_hours',
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
                if (!empty($venue->image_path)) {
                    $this->deleteImage($venue->image_path);
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
                $this->deleteAllAdditionalImages($venue);

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
            } elseif (isset($data['additional_images']) && is_array($data['additional_images'])) {
                $filteredImages = array_filter($data['additional_images'], function ($image) {
                    return !is_null($image) && $image !== '';
                });

                if (!empty($filteredImages)) {
                    $this->deleteAllAdditionalImages($venue);
                    $updateData['additional_images'] = array_values($filteredImages);
                }
            } elseif (isset($data['remove_additional_images']) && $data['remove_additional_images'] === true) {
                $this->deleteAllAdditionalImages($venue);
                $updateData['additional_images'] = [];
            }

            $venue->update($updateData);

            Log::info('Venue updated', ['venue_id' => $venue->id]);

            return $venue->load(['category', 'user']);
        });
    }

    public function delete(VenueV2 $venue): void
    {
        DB::transaction(function () use ($venue) {
            $venue->delete();
            Log::info('Venue soft deleted', ['venue_id' => $venue->id]);
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

        $path = $image->storeAs('venues', $filename, 'public');

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

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $imagePath)) {
            return;
        }

        if (Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }
    }

    private function deleteAllAdditionalImages(VenueV2 $venue): void
    {
        if (!empty($venue->additional_images) && is_array($venue->additional_images)) {
            foreach ($venue->additional_images as $imagePath) {
                if (is_string($imagePath)) {
                    $this->deleteImage($imagePath);
                }
            }
        }
    }

    private function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (VenueV2::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
