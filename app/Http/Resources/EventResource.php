<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'slug' => $this->slug,

            'category' => $this->whenLoaded('category', function () {
                return [
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }),

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
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            'dresscode' => $this->dresscode,
            'min_age' => $this->min_age,
            'max_age' => $this->max_age,

            'organizer_name' => $this->organizer_name,
            'organizer_id' => $this->organizer_id,
            'contact_info' => $this->contact_info,

            'images' => $this->images,
            'cover_image' => $this->cover_image,
            'video_url' => $this->video_url,

            'talents' => TalentResource::collection($this->whenLoaded('talents')),

            'about' => $this->about ?? $this->getDefaultAbout(),
            'location_details' => $this->location_details ?? $this->getDefaultLocationDetails(),
            'booking' => $this->booking ?? $this->getDefaultBooking(),

            'social_links' => $this->social_links,

            'is_live_now' => $this->is_live_now,
            'is_published' => $this->is_published,
            'is_featured' => $this->is_featured,
            'is_cancelled' => $this->is_cancelled,

            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'tags' => $this->tags ?? [],
            'view_count' => $this->view_count ?? 0,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get default about structure.
     *
     * @return array
     */
    protected function getDefaultAbout(): array
    {
        return [
            'accessibility' => ['wheelchair_accessible' => false],
            'planning' => ['ticket_required' => false],
            'services' => ['wifi' => false],
            'amenities' => ['bar' => false],
            'children' => ['suitable_for_children' => false],
            'description' => null,
            'rules' => [],
            'tips' => [],
        ];
    }

    /**
     * Get default location details structure.
     *
     * @return array
     */
    protected function getDefaultLocationDetails(): array
    {
        return [
            'full_address' => $this->address ?? '',
            'directions' => null,
            'public_transport' => null,
            'parking_info' => null,
            'map_url' => null,
            'venue_website' => null,
        ];
    }

    /**
     * Get default booking structure.
     *
     * @return array
     */
    protected function getDefaultBooking(): array
    {
        return [
            'required' => false,
            'ticket_url' => null,
            'capacity' => null,
            'waiting_list_available' => false,
        ];
    }
}
