<?php

namespace App\Support\Recurrence;

/**
 * Recurrence type discriminator stored on recurring_series.recurrence_type.
 *
 * Each type defines which keys are expected inside recurrence_rules (JSON).
 * New types can be added without database migrations.
 */
final class RecurrenceType
{
    public const WEEKLY = 'weekly';

    public const BIWEEKLY = 'biweekly';

    public const MONTHLY = 'monthly';

    public const YEARLY = 'yearly';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::WEEKLY,
            self::BIWEEKLY,
            self::MONTHLY,
            self::YEARLY,
        ];
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    /**
     * Types supported by instance generation and propagation (V2 API).
     *
     * @return list<string>
     */
    public static function v2Supported(): array
    {
        return [
            self::WEEKLY,
        ];
    }
}
