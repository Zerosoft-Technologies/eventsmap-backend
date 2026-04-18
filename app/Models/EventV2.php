<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * EventV2 Model - Version 2 Events for Events Map Platform
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $slug
 * @property int $category_id
 * @property \Carbon\Carbon $event_date
 * @property string $start_time
 * @property string $end_time
 * @property string $address
 * @property float $latitude
 * @property float $longitude
 * @property string $dress_code
 * @property string $age_limit
 * @property string $entrance_status
 * @property string|null $image_path
 * @property int|null $venue_id
 * @property string $status
 * @property bool $is_free_package
 * @property int $view_count
 * @property int $like_count
 * @property bool $is_approved
 * @property \Carbon\Carbon|null $approved_at
 * @property int|null $approved_by
 * @property string|null $suspension_reason
 * @property \Carbon\Carbon|null $suspended_at
 * @property int|null $suspended_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @property-read string $computed_status Dynamic status based on date/time
 * @property-read \Carbon\Carbon $event_start_datetime Full start datetime
 * @property-read \Carbon\Carbon $event_end_datetime Full end datetime
 * @property-read bool $is_overnight Whether event spans midnight
 *
 * @property-read User $user
 * @property-read Category $category
 * @property-read Venue|null $venue
 * @property-read \Illuminate\Database\Eloquent\Collection|SubCategory[] $subcategories
 * @property-read \Illuminate\Database\Eloquent\Collection|User[] $organisers
 * @property-read \Illuminate\Database\Eloquent\Collection|Talent[] $talents
 */
class EventV2 extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'events_v2';

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_UPCOMING = 'upcoming';
    const STATUS_LIVE = 'live';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_SUSPENDED = 'suspended';

    const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_UPCOMING,
        self::STATUS_LIVE,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_SUSPENDED,
    ];

    // Entrance status constants
    const ENTRANCE_FREE = 'free';
    const ENTRANCE_PAID = 'paid';
    const ENTRANCE_SOLD_OUT = 'sold_out';
    const ENTRANCE_CANCELLED = 'cancelled';

    const ENTRANCE_STATUSES = [
        self::ENTRANCE_FREE,
        self::ENTRANCE_PAID,
        self::ENTRANCE_SOLD_OUT,
        self::ENTRANCE_CANCELLED,
    ];

    // Dress code constants
    const DRESS_CASUAL = 'casual';
    const DRESS_SMART_CASUAL = 'smart_casual';
    const DRESS_FORMAL = 'formal';
    const DRESS_BLACK_TIE = 'black_tie';
    const DRESS_COSTUME = 'costume';
    const DRESS_THEMED = 'themed';

    const DRESS_CODES = [
        self::DRESS_CASUAL,
        self::DRESS_SMART_CASUAL,
        self::DRESS_FORMAL,
        self::DRESS_BLACK_TIE,
        self::DRESS_COSTUME,
        self::DRESS_THEMED,
    ];

    // Age limit constants
    const AGE_ALL = 'all_ages';

    const AGE_4_PLUS = '4+';
    const AGE_8_PLUS = '8+';
    const AGE_12_PLUS = '12+';
    const AGE_16_PLUS = '16+';
    const AGE_18_PLUS = '18+';
    const AGE_21_PLUS = '21+';
    const AGE_55_PLUS = '55+';
    const AGE_65_PLUS = '65+';

    const AGE_LIMITS = [
        self::AGE_ALL,
        self::AGE_4_PLUS,
        self::AGE_8_PLUS,
        self::AGE_12_PLUS,
        self::AGE_16_PLUS,
        self::AGE_18_PLUS,
        self::AGE_21_PLUS,
        self::AGE_55_PLUS,
        self::AGE_65_PLUS,
    ];

    // Free package limits
    const FREE_MAX_SUBCATEGORIES = 1;
    const FREE_MAX_IMAGES = 1;
    const FREE_MAX_ADVANCE_DAYS = 365;

    // Premium package limits
    const PREMIUM_MIN_SUBCATEGORIES = 1;
    const PREMIUM_MAX_SUBCATEGORIES = 5;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'event_type',
        'category_id',
        'subcategory_ids',
        'event_date',
        'start_date',
        'end_date',
        'start_datetime',
        'end_datetime',
        'start_time',
        'end_time',
        'address',
        'latitude',
        'longitude',
        'dress_code',
        'age_limit',
        'entrance_status',
        'entrance_fee',
        'contact_phone',
        'contact_email',
        'contact_website',
        'description',
        'contact_box_message',
        'venue_details',
        'image_path',
        'additional_images',
        'invited_talents',
        'invited_organisers',
        'invited_venues',
        'venue_id',
        'status',
        'is_free_package',
        'view_count',
        'like_count',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'ticket_url',
        'booking_instructions',
        'event_option',
        'condition_entrance_fee',
        'condition_dress_code',
        'condition_age_limit',
        'is_recurring',
        'is_copy_event',
        'show_upcoming_events',
        'show_past_events',
        'is_approved',
        'approved_at',
        'approved_by',
        'suspension_reason',
        'suspended_at',
        'suspended_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'entrance_fee' => 'decimal:2',
            'additional_images' => 'array',
            'subcategory_ids' => 'array',
            'invited_talents' => 'array',
            'invited_organisers' => 'array',
            'invited_venues' => 'array',
            'is_free_package' => 'boolean',
            'is_recurring' => 'boolean',
            'is_copy_event' => 'boolean',
            'show_upcoming_events' => 'boolean',
            'show_past_events' => 'boolean',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
            'view_count' => 'integer',
            'like_count' => 'integer',
        ];
    }

    /**
     * Attributes to append to model array/JSON.
     */
    protected $appends = ['computed_status', 'is_overnight'];

    // ──────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function subcategories(): BelongsToMany
    {
        return $this->belongsToMany(SubCategory::class, 'event_v2_subcategory', 'event_v2_id', 'subcategory_id')
            ->withTimestamps();
    }

    /**
     * Get subcategories based on the subcategory_ids column
     * This is used instead of the pivot table relationship
     */
    public function getSubcategoriesFromIdsAttribute()
    {
        if (empty($this->subcategory_ids)) {
            return collect([]);
        }
        
        return SubCategory::whereIn('id', $this->subcategory_ids)->get();
    }

    public function organisers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_v2_organiser', 'event_v2_id', 'user_id')
            ->withTimestamps();
    }

    public function talents(): BelongsToMany
    {
        return $this->belongsToMany(Talent::class, 'event_v2_talent', 'event_v2_id', 'talent_id')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function invitedTalents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_invited_talents', 'event_id', 'user_id')
            ->withTimestamps();
    }

    public function invitedOrganisers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_invited_organisers', 'event_id', 'user_id')
            ->withTimestamps();
    }

    public function invitedVenues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class, 'event_invited_venues', 'event_id', 'venue_id')
            ->withTimestamps();
    }

    // ──────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UPCOMING);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_LIVE);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    // ──────────────────────────────────────
    // Admin Relationships
    // ──────────────────────────────────────

    /**
     * Get the admin who approved this event.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the admin who suspended this event.
     */
    public function suspender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    /**
     * Get users who wishlisted this event.
     */
    public function wishlistedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'wishlists', 'event_v2_id', 'user_id');
    }

    /**
     * Get event views.
     */
    public function views(): HasMany
    {
        return $this->hasMany(EventV2View::class, 'event_v2_id');
    }

    /**
     * Get event likes.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(EventV2Like::class, 'event_v2_id');
    }

    /**
     * Get event invitations.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(EventInvitation::class, 'event_id');
    }

    /**
     * Get accepted invitations.
     */
    public function acceptedInvitations(): HasMany
    {
        return $this->hasMany(EventInvitation::class, 'event_id')
            ->where('status', EventInvitation::STATUS_ACCEPTED);
    }

    /**
     * Get chat bans for this event.
     */
    public function chatBans(): HasMany
    {
        return $this->hasMany(ChatBan::class, 'event_id');
    }

    /**
     * Get chat reports for this event.
     */
    public function chatReports(): HasMany
    {
        return $this->hasMany(ChatReport::class, 'event_id');
    }

    /**
     * Get chat moderation logs for this event.
     */
    public function chatModerationLogs(): HasMany
    {
        return $this->hasMany(ChatModerationLog::class, 'event_id');
    }

    // ──────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────

    /**
     * Get the full start datetime.
     */
    public function getEventStartDatetimeAttribute(): Carbon
    {
        if (!empty($this->attributes['start_datetime'])) {
            return Carbon::parse($this->attributes['start_datetime']);
        }

        $baseDate = $this->start_date?->format('Y-m-d') ?? $this->event_date?->format('Y-m-d');
        if ($baseDate === null || $baseDate === '') {
            $baseDate = Carbon::now()->format('Y-m-d');
        }
        $time = ($this->start_time !== null && $this->start_time !== '')
            ? (string) $this->start_time
            : '00:00:00';

        return Carbon::parse($baseDate . ' ' . $time);
    }

    /**
     * Get the full end datetime (handles overnight events).
     */
    public function getEventEndDatetimeAttribute(): Carbon
    {
        if (!empty($this->attributes['end_datetime'])) {
            return Carbon::parse($this->attributes['end_datetime']);
        }

        $start = $this->event_start_datetime;
        $baseDate = $this->end_date?->format('Y-m-d')
            ?? $this->start_date?->format('Y-m-d')
            ?? $this->event_date?->format('Y-m-d');
        if ($baseDate === null || $baseDate === '') {
            $baseDate = $start->format('Y-m-d');
        }
        $time = ($this->end_time !== null && $this->end_time !== '')
            ? (string) $this->end_time
            : '23:59:59';
        $end = Carbon::parse($baseDate . ' ' . $time);

        if ($end->lte($start)) {
            $end->addDay();
        }

        return $end;
    }

    /**
     * Check if this is an overnight event (spans midnight).
     */
    public function getIsOvernightAttribute(): bool
    {
        if (
            $this->start_time === null || $this->start_time === ''
            || $this->end_time === null || $this->end_time === ''
        ) {
            return false;
        }

        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        return $end->lte($start);
    }

    /**
     * Compute dynamic status based on current time and event dates.
     * Priority: suspended > cancelled > completed > live > upcoming > draft
     */
    public function getComputedStatusAttribute(): string
    {
        // Admin-controlled statuses take priority
        if ($this->status === self::STATUS_SUSPENDED) {
            return self::STATUS_SUSPENDED;
        }

        if ($this->status === self::STATUS_CANCELLED) {
            return self::STATUS_CANCELLED;
        }

        if ($this->status === self::STATUS_DRAFT) {
            return self::STATUS_DRAFT;
        }

        // Dynamic status based on time
        $now = Carbon::now();
        $start = $this->event_start_datetime;
        $end = $this->event_end_datetime;

        if ($now->gt($end)) {
            return self::STATUS_COMPLETED;
        }

        if ($now->gte($start) && $now->lte($end)) {
            return self::STATUS_LIVE;
        }

        return self::STATUS_UPCOMING;
    }

    // ──────────────────────────────────────
    // Additional Scopes
    // ──────────────────────────────────────

    /**
     * Scope to only approved events.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope to exclude suspended events.
     */
    public function scopeNotSuspended(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_SUSPENDED);
    }

    /**
     * Scope to only public-visible events (approved, not suspended, not cancelled).
     */
    public function scopePublicVisible(Builder $query): Builder
    {
        return $query->where('is_approved', true)
            ->whereNotIn('status', [self::STATUS_SUSPENDED, self::STATUS_CANCELLED, self::STATUS_DRAFT]);
    }

    /**
     * Scope to filter events within a bounding box (for map).
     */
    public function scopeWithinBbox(Builder $query, float $minLat, float $maxLat, float $minLng, float $maxLng): Builder
    {
        return $query->whereBetween('latitude', [$minLat, $maxLat])
            ->whereBetween('longitude', [$minLng, $maxLng]);
    }

    /**
     * Haversine distance (km). The value passed to acos() is clamped to [-1, 1] so floating-point
     * error does not exceed the domain and trigger PostgreSQL error 22003.
     */
    protected static function haversineDistanceKmSql(): string
    {
        $cosArg = 'GREATEST(-1.0, LEAST(1.0, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))';

        return "(6371 * acos($cosArg))";
    }

    /**
     * Scope to filter events within radius (Haversine formula).
     *
     * @param float $lat Center latitude
     * @param float $lng Center longitude
     * @param float $radiusKm Radius in kilometers
     */
    public function scopeWithinRadius(Builder $query, float $lat, float $lng, float $radiusKm): Builder
    {
        $haversine = self::haversineDistanceKmSql();

        return $query->whereRaw("$haversine <= ?", [$lat, $lng, $lat, $radiusKm]);
    }

    /**
     * Scope to add distance calculation to query.
     */
    public function scopeWithDistance(Builder $query, float $lat, float $lng): Builder
    {
        $haversine = self::haversineDistanceKmSql();

        return $query->selectRaw("*, $haversine as distance_km", [$lat, $lng, $lat]);
    }

    /**
     * Scope for date range filtering.
     */
    public function scopeDateRange(Builder $query, ?string $from = null, ?string $to = null): Builder
    {
        if ($from) {
            $query->where('event_date', '>=', $from);
        }
        if ($to) {
            $query->where('event_date', '<=', $to);
        }
        return $query;
    }

    /**
     * Scope for date overlap filtering.
     *
     * For single-day events in `events_v2`, this is equivalent to:
     *   event_date between [from, to]
     *
     * We keep the overlap semantics to match the frontend's "range includes
     * any event that intersects the filter window".
     */
    public function scopeDateOverlap(Builder $query, ?string $from = null, ?string $to = null): Builder
    {
        if ($from) {
            $query->where('event_date', '>=', $from);
        }
        if ($to) {
            $query->where('event_date', '<=', $to);
        }

        return $query;
    }

    // ──────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────

    /**
     * Check if the given user owns this event.
     */
    public function isOwner(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Check if event is currently live.
     */
    public function isLive(): bool
    {
        return $this->computed_status === self::STATUS_LIVE;
    }

    /**
     * Check if event can be publicly displayed.
     */
    public function isPublicVisible(): bool
    {
        return $this->is_approved
            && !in_array($this->status, [self::STATUS_SUSPENDED, self::STATUS_CANCELLED, self::STATUS_DRAFT]);
    }

    /**
     * Increment view count atomically.
     */
    public function incrementViews(): void
    {
        $this->increment('view_count');
    }

    /**
     * Increment like count atomically.
     */
    public function incrementLikes(): void
    {
        $this->increment('like_count');
    }

    /**
     * Decrement like count atomically.
     */
    public function decrementLikes(): void
    {
        $this->decrement('like_count');
    }
}
