<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventInvitation extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';

    const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
    ];

    const TYPE_TALENT = 'talent';
    const TYPE_ORGANISER = 'organiser';
    const TYPE_VENUE = 'venue';

    const TYPES = [
        self::TYPE_TALENT,
        self::TYPE_ORGANISER,
        self::TYPE_VENUE,
    ];

    const TOKEN_EXPIRY_HOURS = 48;

    protected $fillable = [
        'event_id',
        'sender_id',
        'receiver_id',
        'receiver_type',
        'status',
        'invitation_token',
        'token_expires_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'token_expires_at' => 'datetime',
        ];
    }

    public function isTokenValid(?string $token): bool
    {
        if (empty($token) || empty($this->invitation_token)) {
            return false;
        }

        if (!hash_equals($this->invitation_token, $token)) {
            return false;
        }

        if ($this->token_expires_at && $this->token_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(EventV2::class, 'event_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopeForReceiver($query, int $userId)
    {
        return $query->where('receiver_id', $userId);
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }
}
