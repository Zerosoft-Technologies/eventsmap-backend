<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventInvitationLog extends Model
{
    use HasFactory;

    const ACTION_CREATED = 'invitation_created';
    const ACTION_EMAIL_SENT = 'invitation_email_sent';
    const ACTION_ACCEPTED = 'invitation_accepted';
    const ACTION_REJECTED = 'invitation_rejected';

    const ACTIONS = [
        self::ACTION_CREATED,
        self::ACTION_EMAIL_SENT,
        self::ACTION_ACCEPTED,
        self::ACTION_REJECTED,
    ];

    protected $fillable = [
        'event_invitation_id',
        'event_id',
        'user_id',
        'action',
        'performed_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function eventInvitation(): BelongsTo
    {
        return $this->belongsTo(EventInvitation::class, 'event_invitation_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(EventV2::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public static function log(
        EventInvitation $invitation,
        string $action,
        ?int $performedBy = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'event_invitation_id' => $invitation->id,
            'event_id' => $invitation->event_id,
            'user_id' => $invitation->receiver_id,
            'action' => $action,
            'performed_by' => $performedBy,
            'metadata' => $metadata,
        ]);
    }
}
