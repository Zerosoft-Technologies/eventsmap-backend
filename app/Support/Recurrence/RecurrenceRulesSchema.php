<?php

namespace App\Support\Recurrence;

/**
 * Documents and validates the JSON shape of recurring_series.recurrence_rules.
 *
 * Phase 1 implements weekly only; other types declare required keys for validation
 * so APIs can be added later without schema changes.
 *
 * Common optional keys (all types, future):
 * - time_slots: list of {start_time, end_time} — when absent, times live on event instances.
 * - interval: positive integer between occurrences (default 1; biweekly uses 2).
 */
final class RecurrenceRulesSchema
{
    /** ISO-8601 weekday: 1 = Monday … 7 = Sunday */
    public const KEY_WEEKDAYS = 'weekdays';

    public const KEY_INTERVAL = 'interval';

    public const KEY_DAY_OF_MONTH = 'day_of_month';

    public const KEY_MONTH = 'month';

    /** Future: multiple slots per occurrence day */
    public const KEY_TIME_SLOTS = 'time_slots';

    /** Original events_v2 row used as the series template (optional metadata). */
    public const KEY_SOURCE_EVENT_ID = 'source_event_id';

    /**
     * Required top-level keys per recurrence_type (excluding optional time_slots).
     *
     * @return list<string>
     */
    public static function requiredKeysFor(string $recurrenceType): array
    {
        return match ($recurrenceType) {
            RecurrenceType::WEEKLY => [self::KEY_WEEKDAYS],
            RecurrenceType::BIWEEKLY => [self::KEY_WEEKDAYS],
            RecurrenceType::MONTHLY => [self::KEY_DAY_OF_MONTH],
            RecurrenceType::YEARLY => [self::KEY_MONTH, self::KEY_DAY_OF_MONTH],
            default => [],
        };
    }

    /**
     * Validate recurrence_rules array for a given type.
     *
     * @param  array<string, mixed>  $rules
     * @return list<string>  Error messages (empty = valid)
     */
    public static function validateForType(string $recurrenceType, array $rules): array
    {
        if (! RecurrenceType::isValid($recurrenceType)) {
            return ['Unknown recurrence type.'];
        }

        $errors = [];

        foreach (self::requiredKeysFor($recurrenceType) as $key) {
            if (! array_key_exists($key, $rules)) {
                $errors[] = "recurrence_rules.{$key} is required for {$recurrenceType} recurrence.";
            }
        }

        if (isset($rules[self::KEY_WEEKDAYS])) {
            $errors = array_merge($errors, self::validateWeekdays($rules[self::KEY_WEEKDAYS]));
        }

        if (isset($rules[self::KEY_INTERVAL])) {
            $errors = array_merge($errors, self::validateInterval($rules[self::KEY_INTERVAL]));
        }

        if (isset($rules[self::KEY_DAY_OF_MONTH])) {
            $errors = array_merge($errors, self::validateDayOfMonth($rules[self::KEY_DAY_OF_MONTH]));
        }

        if (isset($rules[self::KEY_MONTH])) {
            $errors = array_merge($errors, self::validateMonth($rules[self::KEY_MONTH]));
        }

        if (isset($rules[self::KEY_TIME_SLOTS])) {
            $errors = array_merge($errors, self::validateTimeSlots($rules[self::KEY_TIME_SLOTS]));
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private static function validateWeekdays(mixed $weekdays): array
    {
        if (! is_array($weekdays) || $weekdays === []) {
            return ['recurrence_rules.weekdays must be a non-empty array of integers (1–7).'];
        }

        $errors = [];
        foreach ($weekdays as $i => $day) {
            if (! is_int($day) && ! (is_string($day) && ctype_digit($day))) {
                $errors[] = "recurrence_rules.weekdays.{$i} must be an integer.";
                continue;
            }
            $n = (int) $day;
            if ($n < 1 || $n > 7) {
                $errors[] = "recurrence_rules.weekdays.{$i} must be between 1 (Monday) and 7 (Sunday).";
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private static function validateInterval(mixed $interval): array
    {
        if (! is_int($interval) && ! (is_string($interval) && ctype_digit($interval))) {
            return ['recurrence_rules.interval must be a positive integer.'];
        }
        if ((int) $interval < 1) {
            return ['recurrence_rules.interval must be at least 1.'];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private static function validateDayOfMonth(mixed $day): array
    {
        if (! is_int($day) && ! (is_string($day) && ctype_digit($day))) {
            return ['recurrence_rules.day_of_month must be an integer between 1 and 31.'];
        }
        $n = (int) $day;
        if ($n < 1 || $n > 31) {
            return ['recurrence_rules.day_of_month must be between 1 and 31.'];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private static function validateMonth(mixed $month): array
    {
        if (! is_int($month) && ! (is_string($month) && ctype_digit($month))) {
            return ['recurrence_rules.month must be an integer between 1 and 12.'];
        }
        $n = (int) $month;
        if ($n < 1 || $n > 12) {
            return ['recurrence_rules.month must be between 1 and 12.'];
        }

        return [];
    }

    /**
     * Future multiple time slots — validated when present; not required in Phase 1.
     *
     * @return list<string>
     */
    private static function validateTimeSlots(mixed $slots): array
    {
        if (! is_array($slots)) {
            return ['recurrence_rules.time_slots must be an array when provided.'];
        }

        $errors = [];
        foreach ($slots as $i => $slot) {
            if (! is_array($slot)) {
                $errors[] = "recurrence_rules.time_slots.{$i} must be an object.";
                continue;
            }
            foreach (['start_time', 'end_time'] as $field) {
                if (! isset($slot[$field]) || ! is_string($slot[$field])) {
                    $errors[] = "recurrence_rules.time_slots.{$i}.{$field} is required.";
                }
            }
        }

        return $errors;
    }
}
