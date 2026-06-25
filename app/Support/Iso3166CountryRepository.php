<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * ISO 3166-1 alpha-2 countries (English names).
 */
final class Iso3166CountryRepository
{
    private const CACHE_KEY = 'reference.iso3166.countries.en';

    /**
     * @return list<array{code: string, name: string}>
     */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $path = app_path('Data/iso3166-countries-en.json');
            if (! is_readable($path)) {
                return [];
            }

            $decoded = json_decode((string) file_get_contents($path), true);
            if (! is_array($decoded)) {
                return [];
            }

            $out = [];
            foreach ($decoded as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                $name = trim((string) ($row['name'] ?? ''));
                if (strlen($code) === 2 && $name !== '') {
                    $out[] = [
                        'code' => $code,
                        'name' => $name,
                        'flag' => self::flagForCode($code),
                    ];
                }
            }

            usort($out, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

            return $out;
        });
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_column(self::all(), 'code');
    }

    public static function nameForCode(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }
        $needle = strtoupper(trim($code));
        foreach (self::all() as $row) {
            if ($row['code'] === $needle) {
                return $row['name'];
            }
        }

        return null;
    }

    public static function resolveCode(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $trimmed = trim($value);
        if (strlen($trimmed) === 2) {
            $upper = strtoupper($trimmed);
            if (in_array($upper, self::codes(), true)) {
                return $upper;
            }
        }

        foreach (self::all() as $row) {
            if (strcasecmp($row['name'], $trimmed) === 0) {
                return $row['code'];
            }
        }

        return null;
    }

    public static function flagForCode(?string $code): string
    {
        if ($code === null || strlen(trim($code)) !== 2) {
            return '';
        }
        $upper = strtoupper(trim($code));
        $chars = str_split($upper);
        if (count($chars) !== 2) {
            return '';
        }

        return mb_chr(0x1F1E6 + ord($chars[0]) - 65)
            .mb_chr(0x1F1E6 + ord($chars[1]) - 65);
    }
}
