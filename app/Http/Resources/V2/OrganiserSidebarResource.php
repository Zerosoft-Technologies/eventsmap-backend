<?php

namespace App\Http\Resources\V2;

use App\Helpers\MediaHelper;
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
            'image' => $this->when($this->image_path, function () {
                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->image_path)) {
                    $galleryImage = \App\Models\GalleryImage::where('image_id', $this->image_path)
                        ->where('user_id', $this->user_id)
                        ->where('is_deleted', false)
                        ->first();

                    return $galleryImage ? MediaHelper::url($galleryImage->file_path) : null;
                } else {
                    return MediaHelper::resolveUrl($this->image_path);
                }
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
