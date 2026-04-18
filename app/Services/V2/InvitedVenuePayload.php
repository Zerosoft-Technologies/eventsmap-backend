<?php

namespace App\Services\V2;

use App\Helpers\MediaHelper;
use App\Models\Venue;

/**
 * Invited venue shape (shared by {@see \App\Http\Resources\V2\InvitedVenueResource} and bulk hydration).
 */
final class InvitedVenuePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Venue $venue): array
    {
        $data = [
            'id' => $venue->id,
            'user_id' => $venue->user_id,
            'name' => $venue->name,
            'slug' => $venue->slug,
            'address' => $venue->address,
            'latitude' => $venue->latitude !== null ? (float) $venue->latitude : null,
            'longitude' => $venue->longitude !== null ? (float) $venue->longitude : null,
            'city' => $venue->city,
            'country' => $venue->country,
            'phone' => $venue->phone,
            'email' => $venue->email,
            'website' => $venue->website,
            'description' => $venue->description,
            'capacity' => $venue->capacity,
            'is_active' => (bool) $venue->is_active,

            'image_path' => $venue->image_path,

            'created_at' => $venue->created_at?->toIso8601String(),
            'updated_at' => $venue->updated_at?->toIso8601String(),
            'deleted_at' => $venue->deleted_at?->toIso8601String(),
        ];

        if ($venue->image_path) {
            $data['cover_image'] = MediaHelper::url($venue->image_path);
        }

        return $data;
    }
}
