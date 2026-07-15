<?php

namespace App\Http\Resources\V2;

use App\Models\RecurringSeries;
use App\Support\Recurrence\RecurrenceRulesSchema;
use App\Support\Recurrence\RecurringSeriesTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecurringSeries */
class RecurringSeriesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $eventTemplate = null;
        try {
            $eventTemplate = RecurringSeriesTemplate::eventTemplate($this->resource);
        } catch (\Throwable) {
            $rules = is_array($this->recurrence_rules) ? $this->recurrence_rules : [];
            $eventTemplate = $rules[RecurringSeriesTemplate::KEY_EVENT_TEMPLATE] ?? null;
        }

        $rules = is_array($this->recurrence_rules) ? $this->recurrence_rules : [];
        $sourceEventId = isset($rules[RecurrenceRulesSchema::KEY_SOURCE_EVENT_ID])
            ? (int) $rules[RecurrenceRulesSchema::KEY_SOURCE_EVENT_ID]
            : null;

        return [
            'id' => $this->id,
            'organizer_id' => $this->organizer_id,
            'recurrence_type' => $this->recurrence_type,
            'recurrence_rules' => $this->recurrence_rules,
            'event_template' => $eventTemplate,
            'source_event_id' => $sourceEventId,
            'timezone' => $this->timezone,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'is_approved' => (bool) $this->is_approved,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'events_count' => $this->whenCounted('events'),
            'organizer' => $this->whenLoaded('organizer', fn () => [
                'id' => $this->organizer->id,
                'name' => $this->organizer->name,
                'email' => $this->organizer->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
