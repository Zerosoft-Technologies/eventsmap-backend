<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subscription */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $intervalLabel = match ($this->billing_interval) {
            'monthly', 'month' => 'Monthly',
            'yearly', 'year' => 'Yearly',
            'one_time' => 'One-time',
            'weekly' => 'Weekly',
            'daily' => 'Daily',
            default => $this->billing_interval
                ? ucfirst(str_replace('_', ' ', (string) $this->billing_interval))
                : null,
        };

        return [
            'id' => $this->id,
            'plan_name' => $this->plan_name,
            'billing_interval' => $this->billing_interval,
            'billing_interval_label' => $intervalLabel,
            'billing_mode' => $this->billing_mode,
            'subscription_status' => $this->subscription_status,
            'payment_status' => $this->payment_status,
            'amount' => $this->amount,
            'amount_formatted' => $this->amount !== null && $this->currency
                ? MoneyFormatter::format((int) $this->amount, (string) $this->currency)
                : null,
            'currency' => $this->currency ? strtoupper((string) $this->currency) : null,
            'quantity' => $this->quantity,
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'trial_start' => $this->trial_start?->toIso8601String(),
            'trial_end' => $this->trial_end?->toIso8601String(),
            'cancel_at' => $this->cancel_at?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'premium_started_at' => $this->created_at_stripe?->toIso8601String(),
            'discount_applied' => $this->discount_applied,
            'coupon_code' => $this->coupon_code,
            'promo_code' => $this->promo_code,
            'payment_method' => [
                'brand' => $this->payment_method_brand,
                'last4' => $this->payment_method_last4,
                'label' => $this->paymentMethodLabel(),
            ],
            'stripe_customer_id' => $this->stripe_customer_id,
            'stripe_subscription_id' => $this->stripe_subscription_id,
            'checkout_session_id' => $this->checkout_session_id,
            'is_recurring' => $this->billing_mode === Subscription::BILLING_MODE_SUBSCRIPTION,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function paymentMethodLabel(): ?string
    {
        if ($this->payment_method_brand && $this->payment_method_last4) {
            return ucfirst((string) $this->payment_method_brand).' •••• '.$this->payment_method_last4;
        }

        return null;
    }
}
