<?php

namespace App\Models;

use App\Support\Recurrence\RecurrenceType;
use App\Support\Recurrence\RecurringSeriesTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Recurring event series definition — materialized instances live in events_v2.
 *
 * @property int $id
 * @property int $organizer_id
 * @property string $recurrence_type
 * @property array<string, mixed> $recurrence_rules
 * @property string $timezone IANA timezone identifier
 * @property \Carbon\Carbon $start_date
 * @property \Carbon\Carbon|null $end_date
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property bool $is_approved
 * @property \Carbon\Carbon|null $approved_at
 * @property int|null $approved_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read User $organizer
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventV2> $events
 */
class RecurringSeries extends Model
{
    protected $table = 'recurring_series';

    protected $fillable = [
        'organizer_id',
        'recurrence_type',
        'recurrence_rules',
        'timezone',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
        'is_approved',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'recurrence_rules' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'organizer_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
            'approved_by' => 'integer',
        ];
    }

    // ──────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Materialized event instances for this series (events_v2 rows).
     */
    public function events(): HasMany
    {
        return $this->hasMany(EventV2::class, 'series_id');
    }

    // ──────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────

    public function scopeForOrganizer(Builder $query, int $organizerId): Builder
    {
        return $query->where('organizer_id', $organizerId);
    }

    public function scopeType(Builder $query, string $recurrenceType): Builder
    {
        return $query->where('recurrence_type', $recurrenceType);
    }

    public function scopeWeekly(Builder $query): Builder
    {
        return $query->where('recurrence_type', RecurrenceType::WEEKLY);
    }

    /**
     * Series active on a given calendar date (inclusive).
     */
    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query->where('start_date', '<=', $date)
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $date);
            });
    }

    /**
     * Series whose date range overlaps [from, to].
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('is_approved', false);
    }

    public function scopeOverlapping(Builder $query, string $from, ?string $to = null): Builder
    {
        $query->where('start_date', '<=', $to ?? $from);

        if ($to !== null) {
            $query->where(function (Builder $q) use ($from) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $from);
            });
        }

        return $query;
    }

    // ──────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────

    public function isWeekly(): bool
    {
        return $this->recurrence_type === RecurrenceType::WEEKLY;
    }

    public function isOwner(User $user): bool
    {
        return (int) $this->organizer_id === (int) $user->id;
    }

    /**
     * @return list<int>
     */
    public function weekdays(): array
    {
        $days = $this->recurrence_rules['weekdays'] ?? [];

        return array_values(array_map('intval', is_array($days) ? $days : []));
    }

    /**
     * @return array<string, mixed>
     */
    public function eventTemplate(): array
    {
        return RecurringSeriesTemplate::eventTemplate($this);
    }
}
