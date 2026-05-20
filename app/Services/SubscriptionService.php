<?php

namespace App\Services;

use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Models\SubscriptionPaymentMethod;
use App\Models\User;
use App\Services\Stripe\SubscriptionAccountPolicy;

class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionAccountPolicy $accountPolicy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getOverviewForUser(User $user): array
    {
        $records = Subscription::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $current = $this->resolveCurrentSubscription($records);
        $isPremiumEntitled = $user->isPremiumAccount() && $user->isAccountActive();

        $defaultPaymentMethod = SubscriptionPaymentMethod::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->first();

        return [
            'account' => [
                'account_type' => $user->account_type,
                'status' => $user->status,
                'premium_started_at' => $user->premium_started_at?->toIso8601String(),
                'is_premium' => $user->isPremiumAccount(),
                'is_premium_active' => $isPremiumEntitled,
                'requires_payment' => $user->requiresPayment(),
                'email_verified' => $user->hasVerifiedEmail(),
            ],
            'billing' => [
                'billing_type' => $user->billing_type,
                'full_name' => $user->full_name,
                'company_name' => $user->company_name,
                'vat_number' => $user->vat_number,
                'vat_validated' => $user->vat_validated,
                'address' => $user->address,
                'postal_code' => $user->postal_code,
                'city' => $user->city,
                'country' => $user->country,
            ],
            'subscription' => $current
                ? (new SubscriptionResource($current))->resolve()
                : null,
            'payment_method' => $defaultPaymentMethod ? [
                'brand' => $defaultPaymentMethod->brand,
                'last4' => $defaultPaymentMethod->last4,
                'exp_month' => $defaultPaymentMethod->exp_month,
                'exp_year' => $defaultPaymentMethod->exp_year,
                'label' => $defaultPaymentMethod->brand && $defaultPaymentMethod->last4
                    ? ucfirst((string) $defaultPaymentMethod->brand).' •••• '.$defaultPaymentMethod->last4
                    : null,
            ] : ($current ? [
                'brand' => $current->payment_method_brand,
                'last4' => $current->payment_method_last4,
                'label' => $current->payment_method_brand && $current->payment_method_last4
                    ? ucfirst((string) $current->payment_method_brand).' •••• '.$current->payment_method_last4
                    : null,
            ] : null),
            'actions' => [
                'can_upgrade' => $user->isFreeAccount()
                    && $user->hasVerifiedEmail()
                    && $user->isAccountActive(),
                'can_retry_payment' => $user->isPremiumAccount()
                    && $user->status === User::STATUS_PENDING_PAYMENT
                    && is_string($user->stripe_customer_id) && $user->stripe_customer_id !== '',
                'can_view_invoices' => $user->isPremiumAccount(),
            ],
            'stripe' => [
                'customer_id' => $user->stripe_customer_id,
                'subscription_id' => $user->stripe_subscription_id,
            ],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Subscription>  $records
     */
    private function resolveCurrentSubscription($records): ?Subscription
    {
        foreach ($records as $record) {
            if ($this->accountPolicy->shouldGrantPremiumPlan(
                $record->subscription_status,
                $record->billing_mode,
                $record->current_period_end,
                $record->ended_at,
            )) {
                return $record;
            }
        }

        return $records->first();
    }
}
