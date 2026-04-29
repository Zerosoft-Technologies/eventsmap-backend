<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    public const BILLING_MODE_SUBSCRIPTION = 'subscription';

    public const BILLING_MODE_ONE_TIME = 'one_time';

    protected $fillable = [
        'user_id',
        'account_id',
        'email',
        'stripe_customer_id',
        'customer_email',
        'customer_name',
        'stripe_subscription_id',
        'stripe_price_id',
        'stripe_product_id',
        'subscription_status',
        'plan_name',
        'billing_interval',
        'currency',
        'amount',
        'quantity',
        'current_period_start',
        'current_period_end',
        'trial_start',
        'trial_end',
        'cancel_at',
        'canceled_at',
        'ended_at',
        'created_at_stripe',
        'updated_at_stripe',
        'latest_invoice_id',
        'latest_payment_intent_id',
        'payment_status',
        'payment_method_brand',
        'payment_method_last4',
        'coupon_code',
        'discount_applied',
        'promo_code',
        'tax_percent',
        'country',
        'checkout_session_id',
        'billing_mode',
        'raw_stripe_payload',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'amount' => 'integer',
            'discount_applied' => 'boolean',
            'tax_percent' => 'decimal:4',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'trial_start' => 'datetime',
            'trial_end' => 'datetime',
            'cancel_at' => 'datetime',
            'canceled_at' => 'datetime',
            'ended_at' => 'datetime',
            'created_at_stripe' => 'datetime',
            'updated_at_stripe' => 'datetime',
            'raw_stripe_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(SubscriptionPaymentMethod::class);
    }

    public function stripeWebhookEvents(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    public function scopeForStripeSubscription($query, string $stripeSubscriptionId)
    {
        return $query->where('stripe_subscription_id', $stripeSubscriptionId);
    }
}
