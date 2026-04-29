<?php

namespace App\Services\Stripe;

use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPaymentMethod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Invoice;
use Stripe\PaymentMethod;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;

class SubscriptionPersistService
{
    public function __construct(
        private readonly StripeClientFactory $clientFactory,
        private readonly SubscriptionUserStateService $userState,
    ) {}

    public function client(): StripeClient
    {
        return $this->clientFactory->make();
    }

    /**
     * @return array<string, mixed>
     */
    public function stripeObjectToArray(object $object): array
    {
        if (method_exists($object, 'toArray')) {
            return $object->toArray();
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(json_encode($object), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function fetchAndSyncSubscription(string $stripeSubscriptionId, ?User $user = null): Subscription
    {
        $sub = $this->client()->subscriptions->retrieve($stripeSubscriptionId, [
            'expand' => [
                'items.data.price.product',
                'latest_invoice.payment_intent',
                'latest_invoice',
                'default_payment_method',
                'customer',
                'discounts',
            ],
        ]);

        return $this->persistStripeSubscription($sub, $user);
    }

    public function persistStripeSubscription(StripeSubscription $sub, ?User $user = null): Subscription
    {
        $user = $this->resolveUserForStripeSubscription($sub, $user);

        $item = $sub->items->data[0] ?? null;
        $price = $item?->price;
        $product = $price && is_object($price->product) ? $price->product : null;
        $customer = is_object($sub->customer) ? $sub->customer : null;
        $customerId = is_string($sub->customer) ? $sub->customer : ($customer?->id ?? '');

        $discountData = $this->extractDiscountFields($sub);
        $pmFields = $this->extractPaymentMethodFields($sub->default_payment_method ?? null);

        $latestInvoice = is_object($sub->latest_invoice) ? $sub->latest_invoice : null;
        $latestInvoiceId = is_string($sub->latest_invoice) ? $sub->latest_invoice : ($latestInvoice?->id);
        $paymentIntentId = null;
        $paymentStatus = null;
        if ($latestInvoice) {
            $pi = $latestInvoice->payment_intent ?? null;
            $paymentIntentId = is_string($pi) ? $pi : ($pi?->id ?? null);
            $paymentStatus = $latestInvoice->status;
        }

        $attributes = [
            'user_id' => $user->id,
            'account_id' => $user->id,
            'email' => $user->email,
            'stripe_customer_id' => $customerId,
            'customer_email' => $customer?->email ?? $user->email,
            'customer_name' => $customer?->name ?? $user->name,
            'stripe_subscription_id' => $sub->id,
            'stripe_price_id' => $price?->id,
            'stripe_product_id' => is_object($price?->product) ? $price->product->id : (is_string($price?->product) ? $price->product : null),
            'subscription_status' => $sub->status,
            'plan_name' => $product?->name ?? $price?->nickname,
            'billing_interval' => $this->normalizeBillingInterval($price),
            'currency' => $sub->currency ?? $price?->currency,
            'amount' => $this->computeAmountCents($item, $price),
            'quantity' => (int) ($item?->quantity ?? 1),
            'current_period_start' => $this->timestampToCarbon($sub->current_period_start ?? null),
            'current_period_end' => $this->timestampToCarbon($sub->current_period_end ?? null),
            'trial_start' => $this->timestampToCarbon($sub->trial_start ?? null),
            'trial_end' => $this->timestampToCarbon($sub->trial_end ?? null),
            'cancel_at' => $this->timestampToCarbon($sub->cancel_at ?? null),
            'canceled_at' => $this->timestampToCarbon($sub->canceled_at ?? null),
            'ended_at' => $this->timestampToCarbon($sub->ended_at ?? null),
            'created_at_stripe' => $this->timestampToCarbon($sub->created ?? null),
            'updated_at_stripe' => now(),
            'latest_invoice_id' => $latestInvoiceId,
            'latest_payment_intent_id' => $paymentIntentId,
            'payment_status' => $paymentStatus,
            'payment_method_brand' => $pmFields['brand'],
            'payment_method_last4' => $pmFields['last4'],
            'coupon_code' => $discountData['coupon_code'],
            'discount_applied' => $discountData['discount_applied'],
            'promo_code' => $discountData['promo_code'],
            'tax_percent' => $this->extractTaxPercent($sub),
            'country' => $customer?->address?->country ?? $user->country,
            'checkout_session_id' => null,
            'billing_mode' => Subscription::BILLING_MODE_SUBSCRIPTION,
            'raw_stripe_payload' => $this->stripeObjectToArray($sub),
        ];

        $model = DB::transaction(function () use ($attributes, $user, $sub) {
            $record = Subscription::query()->updateOrCreate(
                ['stripe_subscription_id' => $sub->id],
                $attributes,
            );

            if ($sub->default_payment_method) {
                $this->upsertPaymentMethod($sub->default_payment_method, $user, $record);
            }

            return $record;
        });

        $this->userState->applyFromSubscriptionRecord($model->fresh(['user']));

        return $model;
    }

    public function syncFromCheckoutSession(Session $session, ?User $user = null): ?Subscription
    {
        $user = $user ?? $this->resolveUserFromSessionCustomer($session);
        if (! $user) {
            Log::warning('Stripe checkout session: user not resolved', ['session' => $session->id]);

            return null;
        }

        if ($session->mode === 'subscription' && $session->subscription) {
            $sid = is_string($session->subscription) ? $session->subscription : $session->subscription->id;

            return $this->fetchAndSyncSubscription($sid, $user);
        }

        return $this->persistOneTimeCheckoutSession($session, $user);
    }

    public function persistOneTimeCheckoutSession(Session $session, User $user): Subscription
    {
        $customerId = is_string($session->customer) ? $session->customer : ($session->customer->id ?? '');
        $pi = $session->payment_intent;
        if (is_string($pi)) {
            $pi = $this->client()->paymentIntents->retrieve($pi, ['expand' => ['payment_method']]);
        }

        $pmFields = $this->extractPaymentMethodFields($pi?->payment_method ?? null);
        $lineItems = $session->line_items ?? null;
        $amountTotal = (int) ($session->amount_total ?? 0);
        $currency = $session->currency ?? 'usd';

        $planName = 'Premium (one-time)';
        if ($lineItems && isset($lineItems->data[0]->description)) {
            $planName = (string) $lineItems->data[0]->description;
        }

        $attributes = [
            'user_id' => $user->id,
            'account_id' => $user->id,
            'email' => $user->email,
            'stripe_customer_id' => $customerId,
            'customer_email' => $user->email,
            'customer_name' => $user->name,
            'stripe_subscription_id' => null,
            'stripe_price_id' => null,
            'stripe_product_id' => null,
            'subscription_status' => $session->payment_status === 'paid' ? 'paid' : ($session->payment_status ?? 'unknown'),
            'plan_name' => $planName,
            'billing_interval' => 'one_time',
            'currency' => $currency,
            'amount' => $amountTotal > 0 ? $amountTotal : null,
            'quantity' => 1,
            'current_period_start' => null,
            'current_period_end' => null,
            'trial_start' => null,
            'trial_end' => null,
            'cancel_at' => null,
            'canceled_at' => null,
            'ended_at' => null,
            'created_at_stripe' => $this->timestampToCarbon($session->created ?? null),
            'updated_at_stripe' => now(),
            'latest_invoice_id' => is_string($session->invoice) ? $session->invoice : ($session->invoice->id ?? null),
            'latest_payment_intent_id' => is_object($session->payment_intent) ? $session->payment_intent->id : ($session->payment_intent ?? $pi?->id),
            'payment_status' => $session->payment_status,
            'payment_method_brand' => $pmFields['brand'],
            'payment_method_last4' => $pmFields['last4'],
            'coupon_code' => null,
            'discount_applied' => false,
            'promo_code' => null,
            'tax_percent' => null,
            'country' => $user->country,
            'checkout_session_id' => $session->id,
            'billing_mode' => Subscription::BILLING_MODE_ONE_TIME,
            'raw_stripe_payload' => $this->stripeObjectToArray($session),
        ];

        $model = DB::transaction(function () use ($attributes, $session, $user, $pi) {
            $record = Subscription::query()->updateOrCreate(
                ['checkout_session_id' => $session->id],
                $attributes,
            );
            if ($pi && $pi->payment_method) {
                $this->upsertPaymentMethod($pi->payment_method, $user, $record);
            }

            return $record;
        });

        $this->userState->applyFromSubscriptionRecord($model->fresh(['user']));

        return $model;
    }

    public function upsertInvoiceFromStripe(Invoice $invoice): ?SubscriptionInvoice
    {
        $stripeSubscriptionId = is_string($invoice->subscription)
            ? $invoice->subscription
            : ($invoice->subscription->id ?? null);

        $internalSubscription = $stripeSubscriptionId
            ? Subscription::query()->forStripeSubscription($stripeSubscriptionId)->first()
            : null;

        $customerId = is_string($invoice->customer) ? $invoice->customer : ($invoice->customer->id ?? null);
        $user = is_string($customerId) ? User::query()->where('stripe_customer_id', $customerId)->first() : null;
        if (! $user) {
            Log::warning('Stripe invoice: user not found', ['invoice' => $invoice->id, 'customer' => $customerId]);

            return null;
        }

        $pi = $invoice->payment_intent ?? null;
        $paymentIntentId = is_string($pi) ? $pi : ($pi?->id ?? null);

        $row = SubscriptionInvoice::query()->updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'subscription_id' => $internalSubscription?->id,
                'user_id' => $user->id,
                'stripe_subscription_id' => $stripeSubscriptionId,
                'stripe_customer_id' => $customerId,
                'status' => $invoice->status,
                'amount_due' => (int) ($invoice->amount_due ?? 0),
                'amount_paid' => (int) ($invoice->amount_paid ?? 0),
                'amount_remaining' => (int) ($invoice->amount_remaining ?? 0),
                'currency' => $invoice->currency,
                'hosted_invoice_url' => $invoice->hosted_invoice_url,
                'invoice_pdf' => $invoice->invoice_pdf,
                'period_start' => $this->timestampToCarbon($invoice->period_start ?? null),
                'period_end' => $this->timestampToCarbon($invoice->period_end ?? null),
                'payment_intent_id' => $paymentIntentId,
                'charge_id' => is_string($invoice->charge) ? $invoice->charge : ($invoice->charge->id ?? null),
                'raw_stripe_payload' => $this->stripeObjectToArray($invoice),
                'created_at_stripe' => $this->timestampToCarbon($invoice->created ?? null),
            ],
        );

        if ($stripeSubscriptionId) {
            try {
                $this->fetchAndSyncSubscription($stripeSubscriptionId, $user);
            } catch (\Throwable $e) {
                Log::error('Failed to refresh subscription after invoice', [
                    'subscription' => $stripeSubscriptionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $row;
    }

    public function updateSubscriptionsForCustomer(object $customer): void
    {
        $customerId = $customer->id ?? null;
        if (! is_string($customerId) || $customerId === '') {
            return;
        }

        $email = $customer->email ?? null;
        $name = $customer->name ?? null;

        Subscription::query()
            ->where('stripe_customer_id', $customerId)
            ->update(array_filter([
                'customer_email' => is_string($email) ? $email : null,
                'customer_name' => is_string($name) ? $name : null,
            ], fn ($v) => $v !== null));
    }

    public function upsertPaymentMethod(PaymentMethod|string|null $pm, User $user, ?Subscription $subscription = null): void
    {
        if ($pm === null) {
            return;
        }

        if (is_string($pm)) {
            try {
                $pm = $this->client()->paymentMethods->retrieve($pm);
            } catch (\Throwable $e) {
                Log::warning('Could not retrieve payment method', ['id' => $pm, 'error' => $e->getMessage()]);

                return;
            }
        }

        $card = $pm->card ?? null;
        SubscriptionPaymentMethod::query()->updateOrCreate(
            ['stripe_payment_method_id' => $pm->id],
            [
                'user_id' => $user->id,
                'subscription_id' => $subscription?->id,
                'brand' => $card?->brand ?? $pm->type,
                'last4' => $card?->last4,
                'exp_month' => $card?->exp_month,
                'exp_year' => $card?->exp_year,
                'is_default' => (bool) $subscription,
                'raw_stripe_payload' => $this->stripeObjectToArray($pm),
            ],
        );
    }

    private function resolveUserForStripeSubscription(StripeSubscription $sub, ?User $user): User
    {
        if ($user) {
            return $user;
        }

        $customerId = is_string($sub->customer) ? $sub->customer : ($sub->customer->id ?? null);
        if (is_string($customerId)) {
            $byCustomer = User::query()->where('stripe_customer_id', $customerId)->first();
            if ($byCustomer) {
                return $byCustomer;
            }
        }

        $metaUserId = $sub->metadata['user_id'] ?? null;
        if ($metaUserId !== null && $metaUserId !== '') {
            $u = User::query()->find((int) $metaUserId);
            if ($u) {
                return $u;
            }
        }

        throw new \RuntimeException('Cannot resolve application user for Stripe subscription '.$sub->id);
    }

    private function resolveUserFromSessionCustomer(Session $session): ?User
    {
        $metaUid = $session->metadata['user_id'] ?? null;
        if ($metaUid !== null && $metaUid !== '') {
            $u = User::query()->find((int) $metaUid);
            if ($u) {
                return $u;
            }
        }

        $customerId = is_string($session->customer) ? $session->customer : ($session->customer->id ?? null);
        if (! is_string($customerId)) {
            return null;
        }

        return User::query()->where('stripe_customer_id', $customerId)->first();
    }

    private function timestampToCarbon(null|int|string $ts): ?Carbon
    {
        if ($ts === null || $ts === '') {
            return null;
        }

        return Carbon::createFromTimestamp((int) $ts);
    }

    private function normalizeBillingInterval(?object $price): ?string
    {
        $recurring = $price?->recurring ?? null;
        if (! $recurring) {
            return null;
        }

        $interval = $recurring->interval ?? null;
        $count = (int) ($recurring->interval_count ?? 1);

        if ($interval === 'month' && $count === 1) {
            return 'monthly';
        }
        if ($interval === 'year' && $count === 1) {
            return 'yearly';
        }
        if ($interval === 'week' && $count === 1) {
            return 'weekly';
        }
        if ($interval === 'day' && $count === 1) {
            return 'daily';
        }

        return is_string($interval) ? $interval.'_'.$count : null;
    }

    private function computeAmountCents(?object $item, ?object $price): ?int
    {
        if ($item && isset($item->quantity, $price->unit_amount)) {
            return (int) $price->unit_amount * (int) $item->quantity;
        }

        return isset($price->unit_amount) ? (int) $price->unit_amount : null;
    }

    /**
     * @return array{coupon_code: ?string, promo_code: ?string, discount_applied: bool}
     */
    private function extractDiscountFields(StripeSubscription $sub): array
    {
        $couponCode = null;
        $promoCode = null;
        $applied = false;

        $discounts = $sub->discounts ?? [];
        if (is_array($discounts) || (is_object($discounts) && isset($discounts->data))) {
            $data = is_array($discounts) ? $discounts : $discounts->data;
            $first = $data[0] ?? null;
            if (is_string($first)) {
                return ['coupon_code' => null, 'promo_code' => null, 'discount_applied' => false];
            }
            if (is_object($first)) {
                $applied = true;
                $coupon = $first->coupon ?? null;
                if (is_object($coupon)) {
                    $couponCode = $coupon->id ?? $coupon->name ?? null;
                }
                $promotion = $first->promotion_code ?? null;
                if (is_object($promotion)) {
                    $promoCode = $promotion->code ?? $promotion->id ?? null;
                }
            }
        }

        return [
            'coupon_code' => is_string($couponCode) ? $couponCode : null,
            'promo_code' => is_string($promoCode) ? $promoCode : null,
            'discount_applied' => $applied,
        ];
    }

    /**
     * @return array{brand: ?string, last4: ?string}
     */
    private function extractPaymentMethodFields(PaymentMethod|string|null $pm): array
    {
        if ($pm === null) {
            return ['brand' => null, 'last4' => null];
        }
        if (is_string($pm)) {
            return ['brand' => null, 'last4' => null];
        }
        $card = $pm->card ?? null;

        return [
            'brand' => $card?->brand ?? $pm->type,
            'last4' => $card?->last4,
        ];
    }

    private function extractTaxPercent(StripeSubscription $sub): ?string
    {
        $rates = $sub->default_tax_rates ?? [];
        if (! is_array($rates) && is_object($rates) && isset($rates->data)) {
            $rates = $rates->data;
        }
        if (! is_array($rates) || $rates === []) {
            return null;
        }
        $first = $rates[0] ?? null;
        if (is_object($first) && isset($first->percentage)) {
            return (string) $first->percentage;
        }

        return null;
    }
}
