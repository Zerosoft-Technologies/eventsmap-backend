<?php

namespace App\Http\Resources\V2;

use App\Models\User;
use App\Services\V2\InvitedUserPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full user profile from `users` for invited talents / organisers (API-safe, no billing secrets).
 */
class InvitedUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return InvitedUserPayload::toArray($user);
    }
}
