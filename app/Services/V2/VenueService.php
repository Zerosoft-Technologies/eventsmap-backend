<?php

namespace App\Services\V2;

use App\Models\EventInvitation;
use App\Models\VenueCategory;
use App\Models\VenueV2;
use App\Support\ProfilePublicationStatus;
use App\Support\PublishStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VenueService
{
    public function __construct(
        private readonly EventInvitationService $eventInvitationService
    ) {}

    public function getUserVenues(User $user)
    {
        $venues = VenueV2::where('user_id', $user->id)
            ->with(['category', 'user', 'venueCategory', 'venueSubcategories'])
            ->orderBy('created_at', 'desc')
            ->get();

        $this->eventInvitationService->hydrateUpcomingAcceptedInvitationEventsOnProfiles($venues, EventInvitation::TYPE_VENUE);

        return $venues;
    }

    public function create(array $data, User $user): VenueV2
    {
        $venue = DB::transaction(function () use ($data, $user) {
            $defaultVenueCategoryId = $data['venue_category_id']
                ?? VenueCategory::query()->where('slug', VenueCategory::MAIN_SLUG)->value('id');

            $venueData = [
                'user_id' => $user->id,
                'status' => ProfilePublicationStatus::DRAFT,
                'publish_status' => PublishStatus::DRAFT,
                'title' => $data['title'],
                'slug' => $this->generateUniqueSlug($data['title']),
                'event_type' => $data['event_type'] ?? 'free',
                'category_id' => $data['category_id'] ?? null,
                'venue_category_id' => $defaultVenueCategoryId,
                'address' => $data['address'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'is_approved' => false,
            ];

            $optionalFields = [
                'subcategory_ids', 'description', 'description_items',
                'allow_dogs', 'allowance_of_dogs', 'wheelchair_accessible', 'accessibility_description', 'parking', 'valet', 'play_area',
                'contact_phone', 'contact_email', 'contact_website',
                'contact_box_message', 'contact_box_design_message', 'show_contact_box',
                'facebook_url', 'instagram_url', 'tiktok_url',
                'opening_hours',
                'show_upcoming_events', 'show_past_events', 'show_photo_map_marker',
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

            if (! empty($data['venue_subcategory_ids'])) {
                $venue->venueSubcategories()->sync($data['venue_subcategory_ids']);
            }

            Log::info('Venue created', ['venue_id' => $venue->id, 'user_id' => $user->id]);

            return $venue->load(['category', 'user', 'venueCategory', 'venueSubcategories']);
        });

        $this->eventInvitationService->hydrateUpcomingAcceptedInvitationEventsOnProfiles([$venue], EventInvitation::TYPE_VENUE);

        return $venue;
    }

    public function update(VenueV2 $venue, array $data, ?Request $request = null): VenueV2
    {
        $venue = DB::transaction(function () use ($venue, $data, $request) {
            $allowedFields = [
                'title', 'event_type', 'category_id', 'subcategory_ids',
                'venue_category_id',
                'status', 'publish_status',
                'address', 'latitude', 'longitude',
                'description', 'description_items',
                'allow_dogs', 'allowance_of_dogs', 'wheelchair_accessible', 'accessibility_description', 'parking', 'valet', 'play_area',
                'contact_phone', 'contact_email', 'contact_website',
                'contact_box_message', 'contact_box_design_message', 'show_contact_box',
                'facebook_url', 'instagram_url', 'tiktok_url',
                'opening_hours',
                'show_upcoming_events', 'show_past_events', 'show_photo_map_marker',
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
            } elseif (array_key_exists('additional_images', $data)) {
                if (is_array($data['additional_images']) && !empty($data['additional_images'])) {
                    $filteredImages = array_filter($data['additional_images'], function ($image) {
                        return !is_null($image) && $image !== '';
                    });

                    if (!empty($filteredImages)) {
                        $this->deleteAllAdditionalImages($venue);
                        $updateData['additional_images'] = array_values($filteredImages);
                    } else {
                        $this->deleteAllAdditionalImages($venue);
                        $updateData['additional_images'] = null;
                    }
                } else {
                    // Empty array or null — clear all additional images
                    $this->deleteAllAdditionalImages($venue);
                    $updateData['additional_images'] = null;
                }
            } elseif (isset($data['remove_additional_images']) && $data['remove_additional_images'] === true) {
                $this->deleteAllAdditionalImages($venue);
                $updateData['additional_images'] = null;
            }

            $venue->update($updateData);

            if (array_key_exists('venue_subcategory_ids', $data)) {
                $venue->venueSubcategories()->sync($data['venue_subcategory_ids'] ?? []);
            }

            Log::info('Venue updated', ['venue_id' => $venue->id]);

            return $venue->load(['category', 'user', 'venueCategory', 'venueSubcategories']);
        });

        $this->eventInvitationService->hydrateUpcomingAcceptedInvitationEventsOnProfiles([$venue], EventInvitation::TYPE_VENUE);

        return $venue;
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
