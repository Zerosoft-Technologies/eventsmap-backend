<?php

namespace App\Http\Resources\Admin;

use App\Helpers\MediaHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminEventV2Resource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'event_type' => $this->event_type,

            'cover_image' => $this->when($this->image_path, function () {
                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->image_path)) {
                    $galleryImage = \App\Models\GalleryImage::where('image_id', $this->image_path)
                        ->where('user_id', $this->user_id)
                        ->where('is_deleted', false)
                        ->first();

                    return $galleryImage ? MediaHelper::url($galleryImage->file_path) : null;
                }

                return MediaHelper::resolveUrl($this->image_path);
            }),

            // Category
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }),

            'subcategory_ids' => $this->subcategory_ids ?? [],
            'subcategories' => $this->when(! empty($this->subcategory_ids), function () {
                return $this->subcategories_from_ids->map(fn ($sc) => [
                    'id' => $sc->id,
                    'name' => $sc->name,
                    'slug' => $sc->slug,
                ]);
            }),

            'invited_talents' => $this->invited_talents ?? [],
            'invited_organisers' => $this->invited_organisers ?? [],
            'invited_venues' => $this->invited_venues ?? [],

            // Additional images
            'additional_images' => $this->when(isset($this->additional_images), function () {
                if (empty($this->additional_images)) {
                    return [];
                }

                return collect($this->additional_images)->map(function ($image) {
                    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $image)) {
                        $galleryImage = \App\Models\GalleryImage::where('image_id', $image)
                            ->where('user_id', $this->user_id)
                            ->where('is_deleted', false)
                            ->first();

                        return $galleryImage ? [
                            'id' => $galleryImage->image_id,
                            'url' => MediaHelper::url($galleryImage->file_path),
                            'caption' => $galleryImage->caption,
                        ] : null;
                    }

                    return ['id' => null, 'url' => MediaHelper::resolveUrl($image), 'caption' => null];
                })->filter()->values();
            }),

            // Date & Time
            'formatted_date' => $this->start_date?->format('Y-m-d') ?? $this->event_date?->format('Y-m-d'),
            'event_date' => $this->event_date?->format('Y-m-d'),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'start_datetime' => $this->start_datetime?->toIso8601String(),
            'end_datetime' => $this->end_datetime?->toIso8601String(),
            'event_start_datetime' => $this->event_start_datetime?->toIso8601String(),
            'event_end_datetime' => $this->event_end_datetime?->toIso8601String(),
            'is_overnight' => $this->is_overnight,

            // Location
            'address' => $this->address,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,

            // Event settings
            'dress_code' => $this->dress_code,
            'age_limit' => $this->age_limit,
            'entrance_status' => $this->entrance_status,
            'entrance_fee' => $this->entrance_fee,

            // Contact
            'description' => $this->description,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'contact_website' => $this->contact_website,
            'contact_box_message' => $this->contact_box_message,
            'venue_details' => $this->venue_details,

            // Social & Links
            'facebook_url' => $this->facebook_url,
            'instagram_url' => $this->instagram_url,
            'tiktok_url' => $this->tiktok_url,
            'ticket_url' => $this->ticket_url,
            'booking_instructions' => $this->booking_instructions,

            // Status (stored + computed)
            'status' => $this->status,
            'computed_status' => $this->computed_status,

            // Venue
            'venue_id' => $this->venue_id,
            'venue' => $this->whenLoaded('venue', function () {
                if (! $this->venue) {
                    return null;
                }

                return [
                    'id' => $this->venue->id,
                    'name' => $this->venue->name,
                    'slug' => $this->venue->slug,
                    'address' => $this->venue->address,
                ];
            }),

            // Owner (admin view includes email)
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),

            // Package & Analytics
            'is_free_package' => $this->is_free_package,
            'view_count' => $this->view_count ?? 0,
            'like_count' => $this->like_count ?? 0,

            // Settings
            'is_recurring' => (bool) ($this->is_recurring ?? false),
            'show_upcoming_events' => (bool) ($this->show_upcoming_events ?? false),
            'show_past_events' => (bool) ($this->show_past_events ?? false),

            // Admin moderation
            'is_approved' => $this->is_approved ?? false,
            'approved_at' => $this->when($this->approved_at, fn () => $this->approved_at?->toIso8601String()),
            'approved_by' => $this->approved_by,
            'suspension_reason' => $this->when($this->status === 'suspended', $this->suspension_reason),
            'suspended_at' => $this->when($this->suspended_at, fn () => $this->suspended_at?->toIso8601String()),
            'suspended_by' => $this->suspended_by,

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->when($this->deleted_at, fn () => $this->deleted_at?->toIso8601String()),
        ];
    }
}
