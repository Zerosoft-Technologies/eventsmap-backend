<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Normalizes talent DOB input and derives {@code age} for storage / API display.
 */
final class TalentDateOfBirth
{
    public const MIN_DATE = '1900-01-01';

    /** @var list<string> */
    private const INPUT_KEYS = ['date_of_birth', 'birth_date', 'birthdate'];

    /**
     * Merge alias keys into {@code date_of_birth} on the request payload.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function mergeAliases(array $input): array
    {
        if (array_key_exists('date_of_birth', $input)) {
            return $input;
        }

        foreach (self::INPUT_KEYS as $key) {
            if ($key === 'date_of_birth') {
                continue;
            }
            if (array_key_exists($key, $input)) {
                $input['date_of_birth'] = $input[$key];
                unset($input[$key]);

                return $input;
            }
        }

        return $input;
    }

    public static function parse(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->startOfDay();
    }

    public static function ageString(?CarbonInterface $dateOfBirth): ?string
    {
        if ($dateOfBirth === null) {
            return null;
        }

        $years = $dateOfBirth->diffInYears(Carbon::now()->startOfDay());

        return (string) max(0, (int) $years);
    }

    /**
     * When DOB is present, normalize to Y-m-d and fill {@code age} if omitted.
     *
     * @param  array<string, mixed>  $data
     */
    public static function applyAgeFromDateOfBirth(array &$data): void
    {
        if (! array_key_exists('date_of_birth', $data)) {
            return;
        }

        if ($data['date_of_birth'] === null || $data['date_of_birth'] === '') {
            $data['date_of_birth'] = null;

            return;
        }

        $dob = self::parse($data['date_of_birth']);
        if ($dob === null) {
            return;
        }

        $data['date_of_birth'] = $dob->toDateString();
        $data['age'] = self::ageString($dob);
    }

    /**
     * Resolved age for API: stored string, else computed from DOB.
     */
    public static function resolvedAge(?string $storedAge, mixed $dateOfBirth): ?string
    {
        if ($storedAge !== null && $storedAge !== '') {
            return $storedAge;
        }

        $dob = $dateOfBirth instanceof CarbonInterface
            ? $dateOfBirth
            : self::parse($dateOfBirth);

        return self::ageString($dob);
    }

    public static function toApiDate(mixed $dateOfBirth): ?string
    {
        if ($dateOfBirth === null || $dateOfBirth === '') {
            return null;
        }

        if ($dateOfBirth instanceof CarbonInterface) {
            return $dateOfBirth->toDateString();
        }

        return self::parse($dateOfBirth)?->toDateString();
    }
}
