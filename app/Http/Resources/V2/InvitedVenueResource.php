<?php

namespace App\Http\Resources\V2;

use App\Models\Venue;
use App\Services\V2\InvitedVenuePayload;
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
        /** @var Venue $venue */
        $venue = $this->resource;

        return InvitedVenuePayload::toArray($venue);
    }
}
