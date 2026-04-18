<?php

namespace App\Http\Resources\V2;

use App\Helpers\MediaHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full venue row from `venues` for invited venue IDs on events.
 */
class InvitedVenueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'city' => $this->city,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'is_active' => (bool) $this->is_active,

            'image_path' => $this->image_path,
            'cover_image' => $this->when($this->image_path, function () {
                return MediaHelper::url($this->image_path);
            }),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
