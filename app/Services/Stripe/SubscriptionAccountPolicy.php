<?php

namespace App\Services\Stripe;

use App\Models\Subscription;
use Carbon\Carbon;

/**
 * Decides premium entitlement and downgrades from normalized Stripe / billing state.
 */
final class SubscriptionAccountPolicy
{
    public function shouldGrantPremiumPlan(
        ?string $subscriptionStatus,
        ?string $billingMode,
        ?Carbon $currentPeriodEnd,
        ?Carbon $endedAt,
    ): bool {
        if ($billingMode === Subscription::BILLING_MODE_ONE_TIME) {
            return in_array($subscriptionStatus, ['paid', 'complete', 'succeeded'], true);
        }

        if ($subscriptionStatus === null) {
            return false;
        }

        if (in_array($subscriptionStatus, ['active', 'trialing'], true)) {
            return true;
        }

        if ($subscriptionStatus === 'past_due') {
            return true;
        }

        if ($subscriptionStatus === 'canceled' && $currentPeriodEnd && $currentPeriodEnd->isFuture()) {
            return true;
        }

        return false;
    }

    public function shouldDowngradeToFree(
        ?string $subscriptionStatus,
        ?string $billingMode,
        ?Carbon $currentPeriodEnd,
        ?Carbon $endedAt,
    ): bool {
        if ($billingMode === Subscription::BILLING_MODE_ONE_TIME) {
            return false;
        }

        if ($endedAt !== null) {
            return true;
        }

        if ($subscriptionStatus === null) {
            return false;
        }

        if (in_array($subscriptionStatus, ['incomplete', 'incomplete_expired'], true)) {
            return $subscriptionStatus === 'incomplete_expired';
        }

        if ($subscriptionStatus === 'paused') {
            return true;
        }

        if ($subscriptionStatus === 'unpaid') {
            return true;
        }

        if ($subscriptionStatus === 'canceled') {
            if ($currentPeriodEnd && $currentPeriodEnd->isFuture()) {
                return false;
            }

            return true;
        }

        return false;
    }

    public function shouldMarkPaymentPending(?string $subscriptionStatus): bool
    {
        return in_array($subscriptionStatus, ['past_due', 'unpaid'], true);
    }
}
