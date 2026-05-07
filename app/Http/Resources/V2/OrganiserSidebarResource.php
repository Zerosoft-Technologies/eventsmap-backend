<?php

namespace App\Http\Resources\V2;

use App\Support\ProfilePublicationStatus;
use App\Support\PublishStatus;
use App\Support\V2ProfileCoverImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganiserSidebarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->title, // Using 'name' as requested in the response format
            'account_type' => $this->event_type, // Map event_type to account_type
            'email' => $this->contact_email,
            'phone' => $this->contact_phone,
            'address' => $this->address,
            'status' => $this->status ?? ProfilePublicationStatus::DRAFT,
            'status_label' => ProfilePublicationStatus::labels()[$this->status ?? ProfilePublicationStatus::DRAFT]
                ?? ($this->status ?? ProfilePublicationStatus::DRAFT),
            'publish_status' => $this->publish_status ?? PublishStatus::DRAFT,
            'publish_status_label' => PublishStatus::labels()[$this->publish_status ?? PublishStatus::DRAFT]
                ?? ($this->publish_status ?? PublishStatus::DRAFT),
            'image' => V2ProfileCoverImage::coverImageUrl($this->resource, null),
            'image_path' => V2ProfileCoverImage::effectiveStoredPathForProfile($this->resource, null),
            'profile_image' => V2ProfileCoverImage::coverImageUrl($this->resource, null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
