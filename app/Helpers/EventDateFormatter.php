<?php

namespace App\Helpers;

use Carbon\Carbon;

class EventDateFormatter
{
    public static function format(
        string $start,
        string $end,
        ?string $timezone = null
    ): string {
        // DB timezone (ALWAYS UTC)
        $startDate = Carbon::parse($start, 'UTC');
        $endDate   = Carbon::parse($end, 'UTC');

        // Display timezone (CET / CEST handled automatically)
        $displayTz = 'Europe/Berlin';

        $startDate->setTimezone($displayTz);
        $endDate->setTimezone($displayTz);

        // CET or CEST label
        $tzLabel = $startDate->isDST() ? 'CEST' : 'CET';

        return sprintf(
            '%s %s %s, %s - %s (%s)',
            $startDate->format('D'),
            $startDate->format('d'),
            $startDate->format('M'),
            $startDate->format('g:i A'),
            $endDate->format('g:i A'),
            $tzLabel
        );
    }
}
