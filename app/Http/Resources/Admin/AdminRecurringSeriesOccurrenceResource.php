<?php

namespace App\Http\Resources\Admin;

use App\Models\EventV2;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EventV2 */
class AdminRecurringSeriesOccurrenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'event_date' => $this->event_date?->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'start_datetime' => $this->start_datetime?->toIso8601String(),
            'status' => $this->status,
            'computed_status' => $this->computed_status,
            'is_approved' => (bool) $this->is_approved,
            'is_modified' => (bool) $this->is_modified,
            'series_id' => $this->series_id,
            'invitations_total_count' => (int) ($this->invitations_total_count ?? 0),
            'invitations_accepted_count' => (int) ($this->invitations_accepted_count ?? 0),
            'invitations_pending_count' => (int) ($this->invitations_pending_count ?? 0),
            'configured_invitees_count' => $this->configuredInviteesCount(),
        ];
    }

    private function configuredInviteesCount(): int
    {
        $talents = is_array($this->invited_talents) ? count($this->invited_talents) : 0;
        $organisers = is_array($this->invited_organisers) ? count($this->invited_organisers) : 0;
        $venues = is_array($this->invited_venues) ? count($this->invited_venues) : 0;

        return $talents + $organisers + $venues;
    }
}
