<?php

namespace App\Services\Stripe;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SubscriptionUserStateService
{
    public function __construct(
        private readonly SubscriptionAccountPolicy $policy,
    ) {}

    public function applyFromSubscriptionRecord(Subscription $record, ?User $user = null): void
    {
        $user ??= $record->user;
        if (! $user) {
            return;
        }

        $grant = $this->policy->shouldGrantPremiumPlan(
            $record->subscription_status,
            $record->billing_mode,
            $record->current_period_end,
            $record->ended_at,
        );

        $downgrade = $this->policy->shouldDowngradeToFree(
            $record->subscription_status,
            $record->billing_mode,
            $record->current_period_end,
            $record->ended_at,
        );

        $pending = $this->policy->shouldMarkPaymentPending($record->subscription_status)
            && $record->billing_mode === Subscription::BILLING_MODE_SUBSCRIPTION;

        DB::transaction(function () use ($user, $record, $grant, $downgrade, $pending): void {
            $updates = [];

            if ($downgrade) {
                $updates['account_type'] = User::ACCOUNT_FREE;
                $updates['status'] = User::STATUS_ACTIVE;
                $updates['stripe_subscription_id'] = null;
            } elseif ($grant) {
                $updates['account_type'] = User::ACCOUNT_PREMIUM;
                if ($pending) {
                    $updates['status'] = User::STATUS_PENDING_PAYMENT;
                } else {
                    $updates['status'] = User::STATUS_ACTIVE;
                }
                if ($record->stripe_subscription_id) {
                    $updates['stripe_subscription_id'] = $record->stripe_subscription_id;
                }
                if ($user->premium_started_at === null) {
                    $updates['premium_started_at'] = now();
                }
            }

            if ($record->stripe_customer_id && empty($user->stripe_customer_id)) {
                $updates['stripe_customer_id'] = $record->stripe_customer_id;
            }

            if ($record->checkout_session_id && empty($user->stripe_session_id)) {
                $updates['stripe_session_id'] = $record->checkout_session_id;
            }

            if ($updates !== []) {
                $user->update($updates);
            }
        });
    }

    public function applyPaymentSucceeded(User $user): void
    {
        if ($user->account_type !== User::ACCOUNT_PREMIUM) {
            return;
        }

        $user->update([
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    public function applyPaymentFailed(User $user): void
    {
        if ($user->account_type !== User::ACCOUNT_PREMIUM) {
            return;
        }

        $user->update([
            'status' => User::STATUS_PENDING_PAYMENT,
        ]);
    }
}
