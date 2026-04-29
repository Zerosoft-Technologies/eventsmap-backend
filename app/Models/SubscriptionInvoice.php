<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
    protected $fillable = [
        'subscription_id',
        'user_id',
        'stripe_invoice_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'status',
        'amount_due',
        'amount_paid',
        'amount_remaining',
        'currency',
        'hosted_invoice_url',
        'invoice_pdf',
        'period_start',
        'period_end',
        'payment_intent_id',
        'charge_id',
        'raw_stripe_payload',
        'created_at_stripe',
    ];

    protected function casts(): array
    {
        return [
            'amount_due' => 'integer',
            'amount_paid' => 'integer',
            'amount_remaining' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'created_at_stripe' => 'datetime',
            'raw_stripe_payload' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
