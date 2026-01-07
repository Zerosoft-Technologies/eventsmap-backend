<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventLocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locationDetails = $this->location_details ?? [];

        return [
            'venue_name' => $this->venue_name,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'full_address' => $locationDetails['full_address'] ?? $this->buildFullAddress(),
            'directions' => $locationDetails['directions'] ?? null,
            'public_transport' => $locationDetails['public_transport'] ?? null,
            'parking_info' => $locationDetails['parking_info'] ?? null,
            'map_url' => $locationDetails['map_url'] ?? $this->generateMapUrl(),
            'venue_website' => $locationDetails['venue_website'] ?? null,
        ];
    }

    /**
     * Build full address from components.
     *
     * @return string
     */
    protected function buildFullAddress(): string
    {
        $parts = array_filter([
            $this->venue_name,
            $this->address,
            $this->city,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Generate Google Maps URL if coordinates are available.
     *
     * @return string|null
     */
    protected function generateMapUrl(): ?string
    {
        if ($this->latitude && $this->longitude) {
            return "https://www.google.com/maps?q={$this->latitude},{$this->longitude}";
        }

        return null;
    }
}
