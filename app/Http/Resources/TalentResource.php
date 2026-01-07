<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TalentResource extends JsonResource
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
            'name' => $this->name,
            'image' => $this->image,
            'role' => $this->whenPivotLoaded('event_talent', function () {
                return $this->pivot->role;
            }),
            'bio' => $this->bio,
            'social_links' => $this->social_links ?? [
                'spotify' => null,
                'instagram' => null,
                'website' => null,
            ],
        ];
    }
}
