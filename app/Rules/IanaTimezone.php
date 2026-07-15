<?php

namespace App\Rules;

use Closure;
use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ensures the value is a valid IANA timezone identifier (e.g. Europe/Amsterdam).
 * Rejects fixed UTC offsets such as UTC+02:00 or Etc/GMT+2 style abuse where inappropriate.
 */
class IanaTimezone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute must be a valid IANA timezone name.');

            return;
        }

        if (preg_match('/^[+-]\d{2}:\d{2}$/', $value) || preg_match('/^UTC[+-]/i', $value)) {
            $fail('The :attribute must be an IANA timezone name, not a UTC offset.');

            return;
        }

        static $identifiers = null;
        if ($identifiers === null) {
            $identifiers = array_flip(DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC));
        }

        if (! isset($identifiers[$value])) {
            $fail('The :attribute must be a valid IANA timezone identifier.');
        }
    }
}
