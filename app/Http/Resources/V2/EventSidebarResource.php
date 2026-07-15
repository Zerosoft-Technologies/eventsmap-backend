<?php

namespace App\Http\Resources\V2;

use App\Helpers\MediaHelper;
use App\Support\PublishStatus;
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
            'publish_status' => $this->publish_status ?? PublishStatus::DRAFT,
            'publish_status_label' => PublishStatus::labels()[$this->publish_status ?? PublishStatus::DRAFT]
                ?? ($this->publish_status ?? PublishStatus::DRAFT),
            'is_approved' => $this->is_approved ?? false,
            'series_id' => $this->series_id,
            'is_modified' => (bool) ($this->is_modified ?? false),
            'is_series_instance' => $this->resource->isSeriesInstance(),
            'venue_name' => $this->venue_name,
            'image_url' => $this->when($this->image_path, function () {
                // Check if image_path is a UUID format
                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->image_path)) {
                    // It's a UUID, fetch from gallery
                    $galleryImage = \App\Models\GalleryImage::where('image_id', $this->image_path)
                        ->where('user_id', $this->user_id)
                        ->where('is_deleted', false)
                        ->first();

                    return $galleryImage ? MediaHelper::url($galleryImage->file_path) : null;
                } else {
                    // It's a regular file path
                    return MediaHelper::resolveUrl($this->image_path);
                }
            }),
        ];
    }
}
