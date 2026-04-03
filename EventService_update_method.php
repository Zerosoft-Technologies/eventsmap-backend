<?php

namespace App\Services\V2;

use App\Models\EventV2;
use App\Models\EventV2View;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * EventService - Business logic layer for V2 Events.
 *
 * Handles event CRUD operations, status management, file uploads,
 * and admin moderation actions.
 */
class EventService
{
    /**
     * Delete all additional images for an event
     */
    private function deleteAllAdditionalImages(EventV2 $event): void
    {
        if (!empty($event->additional_images) && is_array($event->additional_images)) {
            foreach ($event->additional_images as $imagePath) {
                if (is_string($imagePath)) {
                    $this->deleteImage($imagePath);
                }
            }
        }
    }

    /**
     * Load an event with subcategories from IDs
     */
    private function loadEventWithSubcategories(EventV2 $event): EventV2
    {
        return $event->load(['category', 'venue', 'organisers', 'talents', 'user'])
            ->setAttribute('subcategories', $event->subcategories_from_ids);
    }

    /**
     * Update an existing event.
     *
     * @param EventV2 $event The event to update
     * @param array $data Validated event data
     * @param ?Request $request The request object
     * @return EventV2 The updated event with relationships loaded
     *
     * @throws \Throwable If database transaction fails
     */
    public function update(EventV2 $event, array $data, ?Request $request = null): EventV2
    {
        return DB::transaction(function () use ($event, $data, $request) {
            $allowedFields = [
                'title', 'event_type', 'category_id', 'subcategory_ids', 'event_date', 'start_time', 'end_time',
                'start_date', 'end_date', 'start_datetime', 'end_datetime',
                'address', 'latitude', 'longitude', 'dress_code', 'age_limit',
                'entrance_status', 'entrance_fee', 'venue_id',
                'contact_phone', 'contact_email', 'contact_website', 'description',
                'contact_box_message', 'venue_details', 'facebook_url', 'instagram_url',
                'tiktok_url', 'ticket_url', 'booking_instructions', 'event_option',
                'condition_entrance_fee', 'condition_dress_code', 'condition_age_limit',
                'invited_talents', 'invited_organisers', 'invited_venues',
                'is_recurring', 'is_copy_event', 'show_upcoming_events', 'show_past_events',
            ];

            $updateData = [];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            // Backward compatibility: events_v2.event_date is still NOT NULL in DB.
            if (array_key_exists('start_date', $data) && !array_key_exists('event_date', $data)) {
                $updateData['event_date'] = $data['start_date'];
            }

            if (array_key_exists('event_type', $data)) {
                $updateData['is_free_package'] = $data['event_type'] === 'free';
            }

            if (!in_array($event->status, [EventV2::STATUS_SUSPENDED, EventV2::STATUS_CANCELLED])) {
                $startDateTime = $data['start_datetime'] ?? (string) $event->event_start_datetime;
                $endDateTime = $data['end_datetime'] ?? (string) $event->event_end_datetime;
                $updateData['status'] = $this->resolveStatus((string) $startDateTime, (string) $endDateTime);
            }

            // Handle main image
            if ($request && $request->hasFile('image_path')) {
                // Delete old main image if exists
                if (!empty($event->image_path)) {
                    $this->deleteImage($event->image_path);
                }
                
                // Store new image
                $file = $request->file('image_path');
                if ($file->getError() !== UPLOAD_ERR_OK) {
                    throw new \Exception('File upload failed: ' . $file->getErrorMessage());
                }
                $updateData['image_path'] = $this->storeImage($file);
            } elseif (isset($data['image_path']) && is_string($data['image_path'])) {
                // Handle UUID reference to existing gallery image
                $updateData['image_path'] = $data['image_path'];
            }

            // Handle additional images
            if ($request && $request->hasFile('additional_images')) {
                // Delete all old additional images
                $this->deleteAllAdditionalImages($event);
                
                // Store new additional images
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
            } elseif (isset($data['remove_additional_images']) && $data['remove_additional_images'] === true) {
                // Remove all additional images if flag is set
                $this->deleteAllAdditionalImages($event);
                $updateData['additional_images'] = [];
            }

            if (array_key_exists('subcategory_ids', $data)) {
                $updateData['subcategory_ids'] = $data['subcategory_ids'];
            }
            if (array_key_exists('invited_talents', $data)) {
                $updateData['invited_talents'] = $data['invited_talents'];
            }
            if (array_key_exists('invited_organisers', $data)) {
                $updateData['invited_organisers'] = $data['invited_organisers'];
            }
            if (array_key_exists('invited_venues', $data)) {
                $updateData['invited_venues'] = $data['invited_venues'];
            }

            $event->update($updateData);

            // Note: subcategory_ids is now stored directly in the column, not using pivot table
            $organiserIds = $data['organiser_ids'] ?? null;
            $talentIds = $data['talent_ids'] ?? null;
            if ($organiserIds !== null) {
                $event->organisers()->sync($organiserIds);
            }
            if ($talentIds !== null) {
                $event->talents()->sync($this->formatTalentSync($talentIds));
            }

            $isFreePackage = array_key_exists('event_type', $data)
                ? $data['event_type'] === 'free'
                : $event->is_free_package;
            
            // Check if any invited IDs are present for premium events
            $hasInvitedIds = !$isFreePackage && (
                (!empty($data['invited_talents'] ?? [])) ||
                (!empty($data['invited_organisers'] ?? [])) ||
                (!empty($data['invited_venues'] ?? []))
            );
            
            if ($hasInvitedIds) {
                $event = $event->fresh();
                $this->createInvitationsForEvent($event, $event->user);
            }

            Log::info('Event updated', ['event_id' => $event->id]);

            return $this->loadEventWithSubcategories($event);
        });
    }

    /**
     * Store event image with secure hashed filename.
     * Sanitizes and hashes the filename to prevent security issues.
     *
     * @param UploadedFile $image The uploaded image file
     * @return string The stored file path
     */
    private function storeImage(UploadedFile $image): string
    {
        try {
            // Generate a secure hashed filename
            $extension = $image->getClientOriginalExtension();
            $hash = Str::random(40);
            $filename = $hash . '.' . strtolower($extension);

            $path = $image->storeAs('events', $filename, 'public');
            
            if (!$path) {
                throw new \Exception('Failed to store image file');
            }
            
            Log::info('Image successfully stored', [
                'original_name' => $image->getClientOriginalName(),
                'stored_path' => $path,
                'size' => $image->getSize()
            ]);
            
            return $path;
        } catch (\Exception $e) {
            Log::error('Error storing image', [
                'error' => $e->getMessage(),
                'file' => $image->getClientOriginalName()
            ]);
            throw $e;
        }
    }

    /**
     * Delete an image from storage.
     *
     * @param string|null $imagePath The image path to delete
     */
    private function deleteImage(?string $imagePath): void
    {
        if ($imagePath && Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
            Log::info('Image deleted', ['path' => $imagePath]);
        }
    }
}
