<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wishlist Model - Tracks user's favorite events.
 *
 * @property int $id
 * @property int $user_id
 * @property int $event_v2_id
 * @property \Carbon\Carbon $created_at
 */
class Wishlist extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event_v2_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the user who wishlisted.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wishlisted event.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(EventV2::class, 'event_v2_id');
    }
}
