<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Invoice extends Model
{
    public const STATUS_ISSUED = 'issued';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'subscription_id',
        'subscription_invoice_id',
        'invoice_number',
        'order_id',
        'stripe_payment_intent',
        'stripe_session_id',
        'stripe_invoice_id',
        'stripe_subscription_id',
        'amount',
        'tax_amount',
        'total_amount',
        'currency',
        'payment_status',
        'status',
        'invoice_pdf_path',
        'billing_name',
        'billing_email',
        'billing_address',
        'product_description',
        'plan_interval',
        'quantity',
        'payment_method',
        'payment_method_brand',
        'payment_method_last4',
        'paid_at',
        'emailed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'quantity' => 'integer',
            'paid_at' => 'datetime',
            'emailed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function subscriptionInvoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class);
    }

    public function pdfUrl(): ?string
    {
        if (! $this->invoice_pdf_path) {
            return null;
        }

        $disk = config('invoice.storage_disk', 'public');

        return Storage::disk($disk)->url($this->invoice_pdf_path);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
