<?php

namespace Tests\Unit;

use App\Models\Subscription;
use App\Services\Stripe\SubscriptionAccountPolicy;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class SubscriptionAccountPolicyTest extends TestCase
{
    public function test_active_subscription_grants_premium(): void
    {
        $policy = new SubscriptionAccountPolicy;

        $this->assertTrue($policy->shouldGrantPremiumPlan(
            'active',
            Subscription::BILLING_MODE_SUBSCRIPTION,
            null,
            null,
        ));
        $this->assertFalse($policy->shouldDowngradeToFree(
            'active',
            Subscription::BILLING_MODE_SUBSCRIPTION,
            null,
            null,
        ));
    }

    public function test_canceled_but_period_end_in_future_still_grants(): void
    {
        $policy = new SubscriptionAccountPolicy;
        $end = Carbon::now()->addMonth();

        $this->assertTrue($policy->shouldGrantPremiumPlan(
            'canceled',
            Subscription::BILLING_MODE_SUBSCRIPTION,
            $end,
            null,
        ));
        $this->assertFalse($policy->shouldDowngradeToFree(
            'canceled',
            Subscription::BILLING_MODE_SUBSCRIPTION,
            $end,
            null,
        ));
    }

    public function test_unpaid_downgrades(): void
    {
        $policy = new SubscriptionAccountPolicy;

        $this->assertTrue($policy->shouldDowngradeToFree(
            'unpaid',
            Subscription::BILLING_MODE_SUBSCRIPTION,
            null,
            null,
        ));
    }

    public function test_one_time_paid_grants(): void
    {
        $policy = new SubscriptionAccountPolicy;

        $this->assertTrue($policy->shouldGrantPremiumPlan(
            'paid',
            Subscription::BILLING_MODE_ONE_TIME,
            null,
            null,
        ));
        $this->assertFalse($policy->shouldDowngradeToFree(
            'paid',
            Subscription::BILLING_MODE_ONE_TIME,
            null,
            null,
        ));
    }

    public function test_past_due_marks_payment_pending_not_downgrade(): void
    {
        $policy = new SubscriptionAccountPolicy;

        $this->assertTrue($policy->shouldMarkPaymentPending('past_due'));
        $this->assertTrue($policy->shouldGrantPremiumPlan(
            'past_due',
            Subscription::BILLING_MODE_SUBSCRIPTION,
            null,
            null,
        ));
    }
}
