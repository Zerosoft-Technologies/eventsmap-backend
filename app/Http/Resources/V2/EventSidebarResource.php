<?php

namespace App\Http\Resources\V2;

use App\Helpers\MediaHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * EventSidebarResource - Minimal event data for sidebar listing.
 *
 * Returns only the fields needed for sidebar/list display.
 */
class EventSidebarResource extends JsonResource
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
            'event_date' => $this->event_date?->format('Y-m-d'),
            'start_time' => $this->start_time,
            'status' => $this->status,
            'computed_status' => $this->computed_status,
            'is_approved' => $this->is_approved ?? false,
            'image_url' => $this->image_path ? MediaHelper::url($this->image_path) : null,
        ];
    }
}
