<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatModerationLog extends Model
{
    use HasFactory;

    const ACTION_WARN = 'warn';
    const ACTION_MUTE = 'mute';
    const ACTION_UNMUTE = 'unmute';
    const ACTION_BAN = 'ban';
    const ACTION_UNBAN = 'unban';
    const ACTION_DELETE_MESSAGE = 'delete_message';

    const ACTIONS = [
        self::ACTION_WARN,
        self::ACTION_MUTE,
        self::ACTION_UNMUTE,
        self::ACTION_BAN,
        self::ACTION_UNBAN,
        self::ACTION_DELETE_MESSAGE,
    ];

    protected $fillable = [
        'action',
        'user_id',
        'event_id',
        'message_id',
        'reason',
        'performed_by',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
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

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
