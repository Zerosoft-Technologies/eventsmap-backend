<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatBan extends Model
{
    use HasFactory;

    const TYPE_MUTE = 'mute';
    const TYPE_BAN = 'ban';

    const TYPES = [
        self::TYPE_MUTE,
        self::TYPE_BAN,
    ];

    protected $fillable = [
        'user_id',
        'event_id',
        'type',
        'reason',
        'banned_by',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(EventV2::class, 'event_id');
    }

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return Carbon::now()->gt($this->expires_at);
    }

    public function isEffective(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            });
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeBans($query)
    {
        return $query->where('type', self::TYPE_BAN);
    }

    public function scopeMutes($query)
    {
        return $query->where('type', self::TYPE_MUTE);
    }
}
