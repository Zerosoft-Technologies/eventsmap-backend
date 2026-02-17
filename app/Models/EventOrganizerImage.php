<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventOrganizerImage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_organizer_id',
        'url',
        'alt_text',
        'caption',
        'is_primary',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'event_organizer_id' => 'integer',
        ];
    }

    /**
     * Get the organizer event that owns the image.
     *
     * @return BelongsTo
     */
    public function eventOrganizer(): BelongsTo
    {
        return $this->belongsTo(EventOrganizer::class);
    }
}
