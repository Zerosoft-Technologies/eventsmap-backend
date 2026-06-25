<?php

namespace App\Support;

/**
 * Talent profiles store up to two ISO 3166-1 alpha-2 codes in the {@see TalentV2::$nationality} column (comma-separated).
 */
final class TalentNationalities
{
    public const MAX_COUNT = 2;

    /**
     * @return list<string>
     */
    public static function parse(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', trim($raw)) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $code = Iso3166CountryRepository::resolveCode($part);
            if ($code !== null && ! in_array($code, $out, true)) {
                $out[] = $code;
            }
            if (count($out) >= self::MAX_COUNT) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>|string|null  $codes
     */
    public static function encode(array|string|null $codes): ?string
    {
        if (is_string($codes)) {
            $codes = self::parse($codes);
        }
        if (! is_array($codes) || $codes === []) {
            return null;
        }

        $normalized = [];
        foreach ($codes as $code) {
            $resolved = Iso3166CountryRepository::resolveCode(is_string($code) ? $code : null);
            if ($resolved !== null && ! in_array($resolved, $normalized, true)) {
                $normalized[] = $resolved;
            }
            if (count($normalized) >= self::MAX_COUNT) {
                break;
            }
        }

        return $normalized === [] ? null : implode(',', $normalized);
    }

    /**
     * @return list<array{code: string, name: string, flag: string}>
     */
    public static function toApiList(?string $raw): array
    {
        $out = [];
        foreach (self::parse($raw) as $code) {
            $out[] = [
                'code' => $code,
                'name' => Iso3166CountryRepository::nameForCode($code) ?? $code,
                'flag' => Iso3166CountryRepository::flagForCode($code),
            ];
        }

        return $out;
    }
}
