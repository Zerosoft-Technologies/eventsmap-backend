<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    protected $fillable = [
        'stripe_event_id',
        'event_type',
        'api_version',
        'user_id',
        'subscription_id',
        'livemode',
        'payload',
        'processing_error',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'payload' => 'array',
            'processed_at' => 'datetime',
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
}
