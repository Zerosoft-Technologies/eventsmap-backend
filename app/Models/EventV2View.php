<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EventV2View Model - Tracks event page views for analytics.
 *
 * @property int $id
 * @property int $event_v2_id
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $referrer
 * @property \Carbon\Carbon $viewed_at
 */
class EventV2View extends Model
{
    public $timestamps = false;

    protected $table = 'event_v2_views';

    protected $fillable = [
        'event_v2_id',
        'user_id',
        'ip_address',
        'user_agent',
        'referrer',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * Get the event this view belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(EventV2::class, 'event_v2_id');
    }

    /**
     * Get the user who viewed (if authenticated).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
