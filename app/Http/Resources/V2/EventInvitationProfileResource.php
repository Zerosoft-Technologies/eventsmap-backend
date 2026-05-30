<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal picker row for inviting talents / organisers / venues to an event.
 *
 * @property int $id Profile primary key ({@see TalentV2}, {@see OrganiserV2}, or {@see VenueV2})
 * @property string $name Display name (profile title)
 * @property string $profile_type talent|organiser|venue
 * @property string $account_type free|premium (from owning user)
 * @property string|null $country ISO or stored country label
 */
class EventInvitationProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'profile_type' => $this->resource['profile_type'],
            'account_type' => $this->resource['account_type'],
            'country' => $this->resource['country'],
        ];
    }
}
