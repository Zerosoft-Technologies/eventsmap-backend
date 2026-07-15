<?php

namespace App\Support\Recurrence;

use App\Models\RecurringSeries;
use App\Support\Recurrence\RecurrenceRulesSchema;
use App\Support\Recurrence\RecurringSeriesHorizon;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Timezone-aware occurrence date calculation for recurring series.
 *
 * Phase 3 implements weekly (+ multiple weekdays). Other types throw until extended.
 */
final class RecurringOccurrenceCalculator
{
    /**
     * @return list<CarbonInterface> Calendar dates (start of day in series TZ) for occurrences
     */
    public static function datesForSeries(RecurringSeries $series): array
    {
        $timezone = $series->timezone;
        $type = $series->recurrence_type;

        if ($type !== RecurrenceType::WEEKLY) {
            throw new InvalidArgumentException("Occurrence calculation for recurrence type [{$type}] is not implemented yet.");
        }

        $rules = is_array($series->recurrence_rules) ? $series->recurrence_rules : [];
        $weekdays = array_map('intval', $rules[RecurrenceRulesSchema::KEY_WEEKDAYS] ?? []);

        if ($weekdays === []) {
            throw new InvalidArgumentException('recurrence_rules.weekdays must be a non-empty array for weekly series.');
        }

        $seriesStart = Carbon::parse($series->start_date->format('Y-m-d'), $timezone)->startOfDay();
        $now = Carbon::now($timezone);

        $effectiveStart = $seriesStart->greaterThan($now) ? $seriesStart : $now->copy()->startOfDay();

        $horizonMonths = RecurringSeriesHorizon::horizonMonths();
        $horizonEnd = $seriesStart->copy()->addMonths($horizonMonths)->endOfDay();

        $rangeEnd = $series->end_date
            ? Carbon::parse($series->end_date->format('Y-m-d'), $timezone)->endOfDay()
            : $horizonEnd->copy();

        if ($rangeEnd->greaterThan($horizonEnd)) {
            $rangeEnd = $horizonEnd->copy();
        }

        if ($effectiveStart->greaterThan($rangeEnd)) {
            return [];
        }

        $dates = [];
        $cursor = $effectiveStart->copy()->startOfDay();
        $endDay = $rangeEnd->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($endDay)) {
            if (in_array((int) $cursor->isoWeekday(), $weekdays, true)) {
                $dates[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @param  array{start_time: string, end_time: string}  $slot
     * @return array{start: CarbonInterface, end: CarbonInterface, start_date: string, end_date: string|null}
     */
    public static function datetimesForSlot(CarbonInterface $occurrenceDate, array $slot, string $timezone): array
    {
        $startTime = self::normalizeTimeString($slot['start_time'] ?? '');
        $endTime = self::normalizeTimeString($slot['end_time'] ?? '');

        if ($startTime === '' || $endTime === '') {
            throw new InvalidArgumentException('Each time slot requires start_time and end_time.');
        }

        $date = $occurrenceDate->format('Y-m-d');
        $start = Carbon::parse("{$date} {$startTime}", $timezone);
        $end = Carbon::parse("{$date} {$endTime}", $timezone);

        $endDate = $date;
        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
            $endDate = $end->format('Y-m-d');
        }

        return [
            'start' => $start,
            'end' => $end,
            'start_date' => $date,
            'end_date' => $endDate,
        ];
    }

    private static function normalizeTimeString(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            return '';
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return $time.':00';
        }

        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $time)) {
            return $time;
        }

        throw new InvalidArgumentException("Invalid time format [{$time}]. Expected H:i or H:i:s.");
    }
}
