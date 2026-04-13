<?php

namespace App\Http\Resources\V2;

use App\Helpers\MediaHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VenueResource extends JsonResource
{
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
                } else {
                    return MediaHelper::url($this->image_path);
                }
            }),

            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }),

            'subcategory_ids' => $this->subcategory_ids ?? [],

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
                            'caption' => $galleryImage->caption
                        ] : null;
                    } else {
                        return [
                            'id' => null,
                            'url' => MediaHelper::url($image),
                            'caption' => null
                        ];
                    }
                })->filter()->values();
            }),

            'subcategories' => $this->when(!empty($this->subcategory_ids), function () {
                return $this->subcategories_from_ids->map(fn ($sc) => [
                    'id' => $sc->id,
                    'name' => $sc->name,
                    'slug' => $sc->slug,
                ]);
            }),

            // Location
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,

            // Contact
            'description' => $this->description,
            'description_items' => $this->description_items ?? [],
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'contact_website' => $this->contact_website,

            // Social
            'facebook_url' => $this->facebook_url,
            'instagram_url' => $this->instagram_url,
            'tiktok_url' => $this->tiktok_url,

            // Venue-specific
            'allow_dogs' => (bool) ($this->allow_dogs ?? false),
            'allowance_of_dogs' => $this->allowance_of_dogs,
            'wheelchair_accessible' => (bool) ($this->wheelchair_accessible ?? false),
            'accessibility_description' => $this->accessibility_description,
            'parking' => (bool) ($this->parking ?? false),
            'valet' => (bool) ($this->valet ?? false),
            'play_area' => (bool) ($this->play_area ?? false),
            'opening_hours' => $this->opening_hours ?? [],

            // Settings
            'show_upcoming_events' => (bool) ($this->show_upcoming_events ?? false),
            'show_past_events' => (bool) ($this->show_past_events ?? false),

            // Owner
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),

            'is_approved' => $this->is_approved ?? false,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
