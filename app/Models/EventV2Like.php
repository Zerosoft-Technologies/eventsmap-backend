<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EventV2Like Model - Tracks event likes for analytics.
 *
 * @property int $id
 * @property int $event_v2_id
 * @property int $user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EventV2Like extends Model
{
    protected $table = 'event_v2_likes';

    protected $fillable = [
        'event_v2_id',
        'user_id',
    ];

    /**
     * Get the event this like belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(EventV2::class, 'event_v2_id');
    }

    /**
     * Get the user who liked.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
