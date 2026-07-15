<?php

namespace App\Support\Recurrence;

use Carbon\Carbon;

/**
 * Generation horizon limits for recurring series (aligned with config/recurring.php).
 */
final class RecurringSeriesHorizon
{
    public static function horizonMonths(): int
    {
        return max(1, (int) config('recurring.generation_horizon_months', 12));
    }

    public static function maxEndDateString(string $startDate, ?string $timezone = null): string
    {
        $tz = $timezone ?? config('app.timezone', 'UTC');

        return Carbon::parse($startDate, $tz)
            ->startOfDay()
            ->addMonths(self::horizonMonths())
            ->format('Y-m-d');
    }

    public static function isEndDateWithinHorizon(string $startDate, string $endDate, ?string $timezone = null): bool
    {
        return $endDate <= self::maxEndDateString($startDate, $timezone);
    }

    public static function horizonLabel(): string
    {
        $months = self::horizonMonths();

        return $months === 12 ? 'one year' : "{$months} months";
    }
}
