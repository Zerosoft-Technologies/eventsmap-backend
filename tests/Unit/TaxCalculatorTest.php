<?php

namespace Tests\Unit;

use App\Support\TaxCalculator;
use PHPUnit\Framework\TestCase;

class TaxCalculatorTest extends TestCase
{
    public function test_from_total_with_vat(): void
    {
        $result = TaxCalculator::fromTotal(12100, 21.0);

        $this->assertSame(10000, $result['subtotal']);
        $this->assertSame(2100, $result['tax_amount']);
        $this->assertSame(12100, $result['total']);
    }

    public function test_from_stripe_amounts(): void
    {
        $result = TaxCalculator::fromStripeAmounts(10000, 2100, 12100, null);

        $this->assertSame(10000, $result['subtotal']);
        $this->assertSame(2100, $result['tax_amount']);
        $this->assertSame(12100, $result['total']);
    }
}
