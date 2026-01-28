<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\TalentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Determine status
        $status = 'draft';
        if ($this->is_archived) {
            $status = 'archived';
        } elseif ($this->is_cancelled) {
            $status = 'cancelled';
        } elseif ($this->is_featured) {
            $status = 'featured';
        } elseif ($this->is_published) {
            $status = 'published';
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'status' => $status,

            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }),
            'category_id' => $this->category_id,

            'subcategory' => $this->whenLoaded('subcategory', function () {
                if (!$this->subcategory) return null;
                return [
                    'id' => $this->subcategory->id,
                    'name' => $this->subcategory->name,
                    'slug' => $this->subcategory->slug,
                ];
            }),
            'subcategory_id' => $this->subcategory_id,

            'price' => $this->price,
            'min_price' => $this->min_price,
            'max_price' => $this->max_price,
            'currency' => $this->currency,

            'start_datetime' => $this->start_datetime?->toIso8601String(),
            'end_datetime' => $this->end_datetime?->toIso8601String(),
            'timezone' => $this->timezone,

            'venue_name' => $this->venue_name,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            'dresscode' => $this->dresscode,
            'age_restriction' => $this->age_restriction,
            'min_age' => $this->min_age,
            'max_age' => $this->max_age,

            'organizer_name' => $this->organizer_name,
            'organizer_id' => $this->organizer_id,
            'contact_info' => $this->contact_info,

            'cover_image' => $this->cover_image,
            'video_url' => $this->video_url,
            'images' => $this->images,

            'talents' => $this->whenLoaded('talents', function () {
                return $this->talents->map(function ($talent) {
                    return [
                        'id' => $talent->id,
                        'name' => $talent->name,
                        'slug' => $talent->slug,
                        'image' => $talent->image,
                        'role' => $talent->pivot->role ?? null,
                        'sort_order' => $talent->pivot->sort_order ?? 0,
                    ];
                });
            }),

            'about' => $this->about,
            'location_details' => $this->location_details,
            'booking' => $this->booking,
            'social_links' => $this->social_links,
            'highlights' => $this->highlights ?? [],
            'requirements' => $this->requirements ?? [],
            'additional_info' => $this->additional_info,
            'accessibility_info' => $this->accessibility_info,

            'is_ticketed' => $this->is_ticketed,
            'is_free' => $this->is_free,
            'capacity' => $this->capacity,
            'registration_url' => $this->registration_url,
            'registration_deadline' => $this->registration_deadline?->toIso8601String(),
            'meta_keywords' => $this->meta_keywords ?? [],
            'internal_notes' => $this->internal_notes,
            'custom_fields' => $this->custom_fields,

            'is_published' => $this->is_published ?? false,
            'is_live_now' => $this->is_live_now,

            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'tags' => $this->tags ?? [],

            'view_count' => $this->view_count ?? 0,

            'morning' => $this->morning,
            'afternoon' => $this->afternoon,
            'evening' => $this->evening,
            'night' => $this->night,

            'published_at' => $this->published_at?->toIso8601String(),
            'featured_at' => $this->featured_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
