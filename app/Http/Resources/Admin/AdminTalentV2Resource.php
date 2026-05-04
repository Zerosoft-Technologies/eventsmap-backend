<?php

namespace App\Http\Resources\Admin;

use App\Helpers\MediaHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminTalentV2Resource extends JsonResource
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

            'talent_category_id' => $this->talent_category_id,
            'talent_category' => $this->whenLoaded('talentCategory', function () {
                return [
                    'id' => $this->talentCategory->id,
                    'name' => $this->talentCategory->name,
                    'slug' => $this->talentCategory->slug,
                ];
            }),

            'talent_subcategory_ids' => $this->whenLoaded('talentSubcategories', function () {
                return $this->talentSubcategories->pluck('id')->values();
            }),

            'talent_subcategories' => $this->whenLoaded('talentSubcategories', function () {
                return $this->talentSubcategories->map(fn ($sc) => [
                    'id' => $sc->id,
                    'name' => $sc->name,
                    'slug' => $sc->slug,
                ]);
            }),

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

            // Location
            'city' => $this->city,
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,

            // Contact
            'description' => $this->description,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'contact_website' => $this->contact_website,
            'contact_box_message' => $this->contact_box_message,
            'contact_box_design_message' => $this->contact_box_design_message,

            // Social
            'facebook_url' => $this->facebook_url,
            'instagram_url' => $this->instagram_url,
            'tiktok_url' => $this->tiktok_url,

            // Talent-specific
            'fan_club_url' => $this->fan_club_url,
            'nationality' => $this->nationality,
            'show_nationality' => $this->show_nationality,
            'age' => $this->age,
            'show_age' => $this->show_age,
            'languages' => $this->languages ?? [],
            'highlights' => $this->highlights,

            // Settings
            'show_upcoming_events' => (bool) ($this->show_upcoming_events ?? false),
            'show_past_events' => (bool) ($this->show_past_events ?? false),

            // Owner (admin view includes email)
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),

            // Moderation
            'is_approved' => $this->is_approved ?? false,

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->when($this->deleted_at, fn () => $this->deleted_at?->toIso8601String()),
        ];
    }
}
