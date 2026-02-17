<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EventOrganizer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'events_organizer';

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($event) {
            // Set static value for location_name if not provided
            if (empty($event->location_name)) {
                $event->location_name = 'Default Location';
            }
        });

        static::updating(function ($event) {
            // Set static value for location_name if being set to null
            if (is_null($event->location_name)) {
                $event->location_name = 'Default Location';
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'status',
        'description',
        'short_description',
        'category',
        'category_id',
        'subcategory_id',
        'price',
        'min_price',
        'max_price',
        'currency',
        'dresscode',
        'min_age',
        'max_age',
        'start_datetime',
        'end_datetime',
        'timezone',
        'is_all_day',
        'is_recurring',
        'venue_name',
        'address',
        'city',
        'location_name',
        'state',
        'postal_code',
        'country',
        'organizer_name',
        'organizer_id',
        'contact_email',
        'contact_phone',
        'contact_info',
        'cover_image',
        'video_url',
        'about',
        'location_details',
        'booking',
        'social_links',
        'highlights',
        'requirements',
        'additional_info',
        'age_restriction',
        'accessibility_info',
        'is_ticketed',
        'is_free',
        'capacity',
        'registration_url',
        'registration_deadline',
        'meta_keywords',
        'internal_notes',
        'custom_fields',
        'is_published',
        'is_featured',
        'is_cancelled',
        'is_archived',
        'meta_title',
        'meta_description',
        'tags',
        'view_count',
        'morning',
        'afternoon',
        'evening',
        'night',
        'published_at',
        'featured_at',
        'cancelled_at',
        'archived_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'price' => 'decimal:2',
            'min_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'min_age' => 'integer',
            'max_age' => 'integer',
            'contact_info' => 'array',
            'about' => 'array',
            'location_details' => 'array',
            'booking' => 'array',
            'social_links' => 'array',
            'tags' => 'array',
            'meta_keywords' => 'array',
            'highlights' => 'array',
            'requirements' => 'array',
            'additional_info' => 'array',
            'custom_fields' => 'array',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'is_cancelled' => 'boolean',
            'is_archived' => 'boolean',
            'is_all_day' => 'boolean',
            'is_recurring' => 'boolean',
            'is_ticketed' => 'boolean',
            'is_free' => 'boolean',
            'capacity' => 'integer',
            'registration_deadline' => 'datetime',
            'view_count' => 'integer',
            'category_id' => 'integer',
            'subcategory_id' => 'integer',
            'organizer_id' => 'integer',
            'morning' => 'boolean',
            'afternoon' => 'boolean',
            'evening' => 'boolean',
            'night' => 'boolean',
            'published_at' => 'datetime',
            'featured_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['is_live_now', 'latitude', 'longitude', 'images'];

    /**
     * Get the category that owns the event.
     * Note: This is a string field, not a relationship
     *
     * @return string|null
     */
    public function getCategoryNameAttribute(): ?string
    {
        return $this->category;
    }

    /**
     * Get the talents for the event.
     *
     * @return BelongsToMany
     */
    public function talents(): BelongsToMany
    {
        return $this->belongsToMany(Talent::class, 'event_organizer_talent')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps()
            ->orderBy('pivot_sort_order');
    }

    /**
     * Get the images for the event.
     *
     * @return HasMany
     */
    public function eventImages(): HasMany
    {
        return $this->hasMany(EventOrganizerImage::class);
    }

    /**
     * Scope: Filter events that are currently live.
     * Uses database-level comparison for accurate filtering.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeLiveNow(Builder $query): Builder
    {
        return $query->where('start_datetime', '<=', DB::raw('NOW()'))
                     ->where('end_datetime', '>=', DB::raw('NOW()'));
    }

    /**
     * Scope: Filter events within a radius from a point.
     * Uses ST_DWithin for PostgreSQL, Haversine formula for SQLite.
     *
     * @param Builder $query
     * @param float $latitude
     * @param float $longitude
     * @param float $radiusKm Radius in kilometers
     * @return Builder
     */
    public function scopeWithinRadius(Builder $query, float $latitude, float $longitude, float $radiusKm): Builder
    {
        if (DB::getDriverName() === 'pgsql') {
            $radiusMeters = $radiusKm * 1000;
            return $query->whereRaw(
                'ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
                [$longitude, $latitude, $radiusMeters]
            );
        } else {
            // SQLite - use Haversine formula
            return $query->whereRaw(
                '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?',
                [$latitude, $longitude, $latitude, $radiusKm]
            );
        }
    }

    /**
     * Scope: Add distance from a point to the query results.
     *
     * @param Builder $query
     * @param float $latitude
     * @param float $longitude
     * @return Builder
     */
    public function scopeWithDistanceFrom(Builder $query, float $latitude, float $longitude): Builder
    {
        if (DB::getDriverName() === 'pgsql') {
            return $query->addSelect([
                DB::raw("ST_Distance(location, ST_SetSRID(ST_MakePoint({$longitude}, {$latitude}), 4326)::geography) as distance_meters"),
            ]);
        } else {
            // SQLite - use Haversine formula to calculate distance in meters
            return $query->addSelect([
                DB::raw("(6371000 * acos(cos(radians({$latitude})) * cos(radians(latitude)) * cos(radians(longitude) - radians({$longitude})) + sin(radians({$latitude})) * sin(radians(latitude)))) as distance_meters"),
            ]);
        }
    }

    /**
     * Scope: Select events with coordinates (for PostgreSQL/PostGIS).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithCoordinates(Builder $query): Builder
    {
        if (DB::getDriverName() === 'pgsql') {
            return $query->addSelect([
                DB::raw('ST_X(location::geometry) as latitude'),
                DB::raw('ST_Y(location::geometry) as longitude'),
            ]);
        }
        
        return $query;
    }

    /**
     * Accessor: Check if event is live now.
     *
     * @return bool
     */
    public function getIsLiveNowAttribute(): bool
    {
        $now = Carbon::now();
        return $this->start_datetime <= $now && $this->end_datetime >= $now;
    }

    /**
     * Accessor: Extract latitude from PostGIS GEOGRAPHY point.
     *
     * @return float|null
     */
    public function getLatitudeAttribute(): ?float
    {
        if (!isset($this->attributes['latitude'])) {
            return null;
        }
        return (float) $this->attributes['latitude'];
    }

    /**
     * Accessor: Extract longitude from PostGIS GEOGRAPHY point.
     *
     * @return float|null
     */
    public function getLongitudeAttribute(): ?float
    {
        if (!isset($this->attributes['longitude'])) {
            return null;
        }
        return (float) $this->attributes['longitude'];
    }

    /**
     * Accessor: Get event images.
     *
     * @return array
     */
    public function getImagesAttribute(): array
    {
        return $this->eventImages()
            ->orderBy('sort_order')
            ->get()
            ->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => MediaHelper::url($image->url),
                    'alt_text' => $image->alt_text,
                    'caption' => $image->caption,
                    'is_primary' => $image->is_primary,
                    'sort_order' => $image->sort_order,
                ];
            })
            ->toArray();
    }

    /**
     * Set location from coordinates.
     *
     * @param float $latitude
     * @param float $longitude
     * @return void
     */
    public function setLocationFromCoordinates(float $latitude, float $longitude): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "UPDATE events_organizer SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?",
                [$longitude, $latitude, $this->id]
            );
        } else {
            // SQLite - update latitude and longitude columns
            DB::statement(
                "UPDATE events_organizer SET latitude = ?, longitude = ? WHERE id = ?",
                [$latitude, $longitude, $this->id]
            );
        }
    }
}
