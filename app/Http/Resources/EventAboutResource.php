<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventAboutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $about = $this->about ?? [];

        return [
            'accessibility' => $about['accessibility'] ?? ['wheelchair_accessible' => false],
            'planning' => $about['planning'] ?? ['ticket_required' => false],
            'services' => $about['services'] ?? ['wifi' => false],
            'amenities' => $about['amenities'] ?? ['bar' => false],
            'children' => $about['children'] ?? ['suitable_for_children' => false],
            'description' => $about['description'] ?? $this->description,
            'rules' => $about['rules'] ?? [],
            'tips' => $about['tips'] ?? [],
        ];
    }
}
