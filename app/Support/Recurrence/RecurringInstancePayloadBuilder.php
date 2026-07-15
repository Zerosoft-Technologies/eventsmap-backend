<?php

namespace App\Support\Recurrence;

use App\Models\RecurringSeries;

/**
 * Builds event instance payloads from series template + occurrence datetimes.
 *
 * Shared by generation and propagation to avoid duplicated field mapping.
 */
final class RecurringInstancePayloadBuilder
{
    /** Fields never copied during propagation (invitations stay per-instance). */
    private const EXCLUDED_FROM_PROPAGATION = [
        'invited_talents',
        'invited_organisers',
        'invited_venues',
    ];

    /**
     * @param  array<string, mixed>  $template
     * @param  array{start: \Carbon\CarbonInterface, end: \Carbon\CarbonInterface, start_date: string, end_date: string|null}  $datetimes
     * @return array<string, mixed>
     */
    public static function build(array $template, RecurringSeries $series, array $datetimes): array
    {
        $start = $datetimes['start'];
        $end = $datetimes['end'];

        // Invitations are per-instance; copy invited_* from template at generation time.
        $payload = $template;

        $payload['start_date'] = $datetimes['start_date'];
        $payload['end_date'] = $datetimes['end_date'];
        $payload['start_datetime'] = $start->format('Y-m-d H:i:s');
        $payload['end_datetime'] = $end->format('Y-m-d H:i:s');
        $payload['event_date'] = $datetimes['start_date'];
        $payload['start_time'] = $start->format('H:i:s');
        $payload['end_time'] = $end->format('H:i:s');
        $payload['series_id'] = $series->id;
        $payload['is_modified'] = false;
        $payload['is_recurring'] = true;

        return $payload;
    }

    /**
     * Payload subset safe to pass to EventService::update() during propagation.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function forUpdate(array $payload): array
    {
        unset(
            $payload['series_id'],
            $payload['is_modified'],
            $payload['is_recurring'],
        );

        foreach (self::EXCLUDED_FROM_PROPAGATION as $field) {
            unset($payload[$field]);
        }

        return $payload;
    }
}
