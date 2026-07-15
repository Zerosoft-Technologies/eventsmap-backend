<?php

namespace App\Http\Resources\Admin;

use App\Models\EventV2;
use App\Models\RecurringSeries;
use App\Services\Admin\AdminRecurringSeriesService;
use App\Support\Recurrence\RecurrenceRulesSchema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecurringSeries */
class AdminRecurringSeriesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rules = is_array($this->recurrence_rules) ? $this->recurrence_rules : [];
        $sourceEventId = isset($rules[RecurrenceRulesSchema::KEY_SOURCE_EVENT_ID])
            ? (int) $rules[RecurrenceRulesSchema::KEY_SOURCE_EVENT_ID]
            : null;
        $sourceEvent = null;

        if ($sourceEventId) {
            $event = EventV2::query()->find($sourceEventId);
            if ($event) {
                $sourceEvent = [
                    'id' => $event->id,
                    'title' => $event->title,
                    'event_date' => $event->event_date?->format('Y-m-d'),
                    'start_time' => $event->start_time,
                    'end_time' => $event->end_time,
                ];
            }
        }

        /** @var AdminRecurringSeriesService $adminService */
        $adminService = app(AdminRecurringSeriesService::class);

        return [
            'id' => $this->id,
            'name' => $adminService->seriesName($this->resource),
            'organizer_id' => $this->organizer_id,
            'recurrence_type' => $this->recurrence_type,
            'recurrence_pattern' => $adminService->recurrencePattern($this->resource),
            'recurrence_rules' => $this->recurrence_rules,
            'source_event_id' => $sourceEventId,
            'source_event' => $sourceEvent,
            'timezone' => $this->timezone,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $adminService->computedStatus($this->resource),
            'is_approved' => (bool) $this->is_approved,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'next_occurrence' => $adminService->nextOccurrence($this->resource),
            'events_count' => $this->whenCounted('events'),
            'organizer' => $this->whenLoaded('organizer', fn () => [
                'id' => $this->organizer->id,
                'name' => $this->organizer->name,
                'email' => $this->organizer->email,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
                'email' => $this->creator?->email,
            ]),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
                'email' => $this->approver->email,
            ] : null),
            'occurrences' => AdminRecurringSeriesOccurrenceResource::collection(
                $this->whenLoaded('events'),
            ),
            'invitation_summary' => $this->when(
                isset($this->invitation_summary),
                fn () => $this->invitation_summary,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
