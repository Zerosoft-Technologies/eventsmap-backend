<?php

namespace App\Http\Resources\V2;

use App\Helpers\MediaHelper;
use App\Http\Resources\V2\EventResource;
use App\Support\ProfilePublicationStatus;
use App\Support\PublishStatus;
use App\Support\V2ProfileCoverImage;
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
            'status' => $this->status ?? ProfilePublicationStatus::DRAFT,
            'status_label' => ProfilePublicationStatus::labels()[$this->status ?? ProfilePublicationStatus::DRAFT]
                ?? ($this->status ?? ProfilePublicationStatus::DRAFT),
            'publish_status' => $this->publish_status ?? PublishStatus::DRAFT,
            'publish_status_label' => PublishStatus::labels()[$this->publish_status ?? PublishStatus::DRAFT]
                ?? ($this->publish_status ?? PublishStatus::DRAFT),
            'event_type' => $this->event_type,

            'image_path' => V2ProfileCoverImage::effectiveStoredPathForProfile($this->resource, null),
            'profile_image' => V2ProfileCoverImage::coverImageUrl($this->resource, null),
            'cover_image' => V2ProfileCoverImage::coverImageUrl($this->resource, null),

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
                            'caption' => $galleryImage->caption,
                        ] : null;
                    } else {
                        return [
                            'id' => null,
                            'url' => MediaHelper::resolveUrl($image),
                            'caption' => null,
                        ];
                    }
                })->filter()->values();
            }),

            'subcategories' => $this->subcategoriesForListing(),

            'venue_category_id' => $this->venue_category_id,

            'venue_category' => $this->whenLoaded('venueCategory', function () {
                return [
                    'id' => $this->venueCategory->id,
                    'name' => $this->venueCategory->name,
                    'slug' => $this->venueCategory->slug,
                ];
            }),

            'venue_subcategory_ids' => $this->whenLoaded('venueSubcategories', function () {
                return $this->venueSubcategories->pluck('id')->values();
            }),

            'venue_subcategories' => $this->whenLoaded('venueSubcategories', function () {
                return $this->venueSubcategories->map(fn ($sc) => [
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
            'contact_box_message' => $this->contact_box_message,
            'contact_box_design_message' => $this->contact_box_design_message,
            'show_contact_box' => (bool) ($this->show_contact_box ?? false),

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
            'show_photo_map_marker' => (bool) ($this->show_photo_map_marker ?? false),

            'upcoming_events' => $this->when(($this->show_upcoming_events ?? false), function () {
                $raw = $this->resource->getAttribute('_upcoming_events');
                if (! is_array($raw) || $raw === []) {
                    return [];
                }

                return EventResource::collection($raw)->toArray(request());
            }),

            'past_events' => $this->when(($this->show_past_events ?? false), function () {
                $raw = $this->resource->getAttribute('_past_events');
                if (! is_array($raw) || $raw === []) {
                    return [];
                }

                return EventResource::collection($raw)->toArray(request());
            }),

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

    /**
     * @return list<array{id: int, name: string, slug: string}>
     */
    protected function subcategoriesForListing(): array
    {
        if ($this->relationLoaded('subcategories') && $this->subcategories !== null && $this->subcategories->isNotEmpty()) {
            return $this->subcategories->map(fn ($sc) => [
                'id' => $sc->id,
                'name' => $sc->name,
                'slug' => $sc->slug,
            ])->values()->all();
        }

        if (empty($this->subcategory_ids)) {
            return [];
        }

        return $this->subcategories_from_ids->map(fn ($sc) => [
            'id' => $sc->id,
            'name' => $sc->name,
            'slug' => $sc->slug,
        ])->values()->all();
    }
}
