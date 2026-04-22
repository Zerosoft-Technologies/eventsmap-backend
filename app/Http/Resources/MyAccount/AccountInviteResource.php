<?php

namespace App\Http\Resources\MyAccount;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array{
 *     event_id:int,
 *     event_title:string,
 *     type:string,
 *     profile_id:int,
 *     name:string,
 *     image_path:string,
 *     slug:string
 * }
 */
class AccountInviteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'event_id' => $this->resource['event_id'],
            'event_title' => $this->resource['event_title'],
            'type' => $this->resource['type'],
            'profile_id' => $this->resource['profile_id'],
            'name' => $this->resource['name'],
            'image_path' => $this->resource['image_path'],
            'slug' => $this->resource['slug'],
        ];
    }
}
