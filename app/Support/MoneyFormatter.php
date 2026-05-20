<?php

namespace App\Support;

final class MoneyFormatter
{
    public static function format(int $amountCents, string $currency): string
    {
        $currency = strtoupper($currency);
        $amount = $amountCents / 100;

        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
        ];

        $symbol = $symbols[$currency] ?? $currency.' ';

        if (in_array($currency, ['EUR', 'USD', 'GBP'], true)) {
            return $symbol.number_format($amount, 2, '.', ',');
        }

        return strtoupper($currency).' '.number_format($amount, 2, '.', ',');
    }
}
