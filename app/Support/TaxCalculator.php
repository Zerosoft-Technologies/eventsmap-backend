<?php

namespace App\Support;

/**
 * Amounts are in the smallest currency unit (e.g. cents).
 */
final class TaxCalculator
{
    /**
     * @return array{subtotal: int, tax_amount: int, total: int, tax_rate: float}
     */
    public static function fromTotal(int $totalCents, float $taxRatePercent): array
    {
        if ($taxRatePercent <= 0) {
            return [
                'subtotal' => $totalCents,
                'tax_amount' => 0,
                'total' => $totalCents,
                'tax_rate' => 0.0,
            ];
        }

        $divisor = 1 + ($taxRatePercent / 100);
        $subtotal = (int) round($totalCents / $divisor);
        $tax = max(0, $totalCents - $subtotal);

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total' => $totalCents,
            'tax_rate' => $taxRatePercent,
        ];
    }

    /**
     * @return array{subtotal: int, tax_amount: int, total: int, tax_rate: float}
     */
    public static function fromSubtotal(int $subtotalCents, float $taxRatePercent): array
    {
        if ($taxRatePercent <= 0) {
            return [
                'subtotal' => $subtotalCents,
                'tax_amount' => 0,
                'total' => $subtotalCents,
                'tax_rate' => 0.0,
            ];
        }

        $tax = (int) round($subtotalCents * ($taxRatePercent / 100));

        return [
            'subtotal' => $subtotalCents,
            'tax_amount' => $tax,
            'total' => $subtotalCents + $tax,
            'tax_rate' => $taxRatePercent,
        ];
    }

    /**
     * @return array{subtotal: int, tax_amount: int, total: int, tax_rate: float}
     */
    public static function fromStripeAmounts(?int $subtotal, ?int $tax, ?int $total, ?float $fallbackTaxRate = null): array
    {
        if ($total !== null && $total > 0 && $tax !== null && $tax >= 0 && $subtotal !== null && $subtotal >= 0) {
            $rate = $subtotal > 0 ? round(($tax / $subtotal) * 100, 4) : 0.0;

            return [
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total' => $total,
                'tax_rate' => $rate,
            ];
        }

        $totalCents = $total ?? $subtotal ?? 0;
        $rate = $fallbackTaxRate ?? (float) config('invoice.default_tax_rate', 0);

        return self::fromTotal($totalCents, $rate);
    }
}
