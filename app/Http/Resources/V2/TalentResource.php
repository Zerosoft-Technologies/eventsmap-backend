<?php

namespace App\Http\Resources\V2;

use App\Helpers\MediaHelper;
use App\Http\Resources\V2\Concerns\HydratesProfileEventLists;
use App\Support\Iso3166CountryRepository;
use App\Support\TalentNationalities;
use App\Support\ProfilePublicationStatus;
use App\Support\PublishStatus;
use App\Support\TalentDateOfBirth;
use App\Support\V2ProfileCoverImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TalentResource extends JsonResource
{
    use HydratesProfileEventLists;

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

            'event_category' => $this->whenLoaded('eventCategory', function () {
                return [
                    'id' => $this->eventCategory->id,
                    'name' => $this->eventCategory->name,
                    'slug' => $this->eventCategory->slug,
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
            'show_contact_box' => (bool) ($this->show_contact_box ?? false),

            // Social
            'facebook_url' => $this->facebook_url,
            'instagram_url' => $this->instagram_url,
            'tiktok_url' => $this->tiktok_url,

            // Talent-specific
            'fan_club_url' => $this->fan_club_url,
            'nationality' => $this->nationality,
            'nationality_name' => Iso3166CountryRepository::nameForCode(
                TalentNationalities::parse($this->nationality)[0] ?? $this->nationality
            ) ?? $this->nationality,
            'nationalities' => TalentNationalities::parse($this->nationality),
            'nationality_list' => TalentNationalities::toApiList($this->nationality),
            'show_nationality' => $this->show_nationality,
            'date_of_birth' => TalentDateOfBirth::toApiDate($this->date_of_birth),
            'age' => TalentDateOfBirth::resolvedAge($this->age, $this->date_of_birth),
            'show_age' => $this->show_age,
            'languages' => $this->languages ?? [],
            'highlights' => $this->highlights,

            // Settings
            'show_upcoming_events' => (bool) ($this->show_upcoming_events ?? false),
            'show_past_events' => (bool) ($this->show_past_events ?? false),
            'show_photo_map_marker' => (bool) ($this->show_photo_map_marker ?? false),

            'upcoming_events' => $this->when(($this->show_upcoming_events ?? false), fn () => $this->hydratedEventListAttribute('_upcoming_events')),

            'past_events' => $this->when(($this->show_past_events ?? false), fn () => $this->hydratedEventListAttribute('_past_events')),

            'user_id' => $this->user_id,

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
        if ($this->relationLoaded('talentSubcategories') && $this->talentSubcategories->isNotEmpty()) {
            return $this->talentSubcategories->map(fn ($sc) => [
                'id' => $sc->id,
                'name' => $sc->name,
                'slug' => $sc->slug,
            ])->values()->all();
        }

        return $this->subcategories_from_ids->map(fn ($sc) => [
            'id' => $sc->id,
            'name' => $sc->name,
            'slug' => $sc->slug,
        ])->values()->all();
    }
}
