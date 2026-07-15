<?php

namespace App\Support\Recurrence;

use App\Models\RecurringSeries;
use InvalidArgumentException;

/**
 * Reads event template and time slots from recurrence_rules JSON (no extra DB columns).
 */
final class RecurringSeriesTemplate
{
    public const KEY_EVENT_TEMPLATE = 'event_template';

    /**
     * @return array<string, mixed>
     */
    public static function eventTemplate(RecurringSeries $series): array
    {
        $rules = is_array($series->recurrence_rules) ? $series->recurrence_rules : [];
        $template = $rules[self::KEY_EVENT_TEMPLATE] ?? null;

        if (! is_array($template) || $template === []) {
            throw new InvalidArgumentException(
                'Series is missing recurrence_rules.event_template required for instance generation.'
            );
        }

        return $template;
    }

    /**
     * @return list<array{start_time: string, end_time: string}>
     */
    public static function timeSlots(RecurringSeries $series): array
    {
        $rules = is_array($series->recurrence_rules) ? $series->recurrence_rules : [];
        $slots = $rules[RecurrenceRulesSchema::KEY_TIME_SLOTS] ?? null;

        if (is_array($slots) && $slots !== []) {
            return array_values($slots);
        }

        $template = self::eventTemplate($series);
        $start = $template['start_time'] ?? null;
        $end = $template['end_time'] ?? null;

        if (! is_string($start) || ! is_string($end) || $start === '' || $end === '') {
            throw new InvalidArgumentException(
                'Series requires recurrence_rules.time_slots or event_template.start_time/end_time.'
            );
        }

        return [['start_time' => $start, 'end_time' => $end]];
    }

    /**
     * Merge top-level event_template into recurrence_rules for persistence.
     *
     * @param  array<string, mixed>  $recurrenceRules
     * @param  array<string, mixed>  $eventTemplate
     * @return array<string, mixed>
     */
    public static function mergeTemplateIntoRules(array $recurrenceRules, array $eventTemplate): array
    {
        $recurrenceRules[self::KEY_EVENT_TEMPLATE] = $eventTemplate;

        if (empty($recurrenceRules[RecurrenceRulesSchema::KEY_TIME_SLOTS])
            && ! empty($eventTemplate['start_time'])
            && ! empty($eventTemplate['end_time'])) {
            $recurrenceRules[RecurrenceRulesSchema::KEY_TIME_SLOTS] = [[
                'start_time' => $eventTemplate['start_time'],
                'end_time' => $eventTemplate['end_time'],
            ]];
        }

        return $recurrenceRules;
    }
}
