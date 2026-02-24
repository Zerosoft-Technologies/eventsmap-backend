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

class Event extends Model
{
    use HasFactory, SoftDeletes;

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
        'custom_fields',
        'is_published',
        'is_featured',
        'is_cancelled',
        'is_archived',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'internal_notes',
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
     *
     * @return BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the subcategory that belongs to the event.
     *
     * @return BelongsTo
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class);
    }

    /**
     * Get the talents for the event.
     *
     * @return BelongsToMany
     */
    public function talents(): BelongsToMany
    {
        return $this->belongsToMany(Talent::class, 'event_talent')
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
        return $this->hasMany(EventImage::class)->ordered();
    }

    /**
     * Get the organizer (user) of the event.
     *
     * @return BelongsTo
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * Accessor: Get images array from relationship.
     *
     * @return array
     */
    public function getImagesAttribute(): array
    {
        return $this->eventImages->pluck('url')->toArray();
    }

    /**
     * Accessor: Determine if the event is currently live.
     * An event is LIVE NOW if current timestamp is between start and end datetime.
     *
     * @return bool
     */
    public function getIsLiveNowAttribute(): bool
    {
        $now = Carbon::now();
        return $now->between($this->start_datetime, $this->end_datetime);
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
     * Scope: Filter events by date range.
     *
     * @param Builder $query
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return Builder
     */
    public function scopeDateRange(Builder $query, ?string $fromDate = null, ?string $toDate = null): Builder
    {
        if ($fromDate) {
            $query->where('start_datetime', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('start_datetime', '<=', $toDate);
        }
        return $query;
    }

    /**
     * Scope: Filter events by price range.
     *
     * @param Builder $query
     * @param float|null $minPrice
     * @param float|null $maxPrice
     * @return Builder
     */
    public function scopePriceRange(Builder $query, ?float $minPrice = null, ?float $maxPrice = null): Builder
    {
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }
        return $query;
    }

    /**
     * Scope: Filter events by category slug.
     *
     * @param Builder $query
     * @param string $categorySlug
     * @return Builder
     */
    public function scopeCategory(Builder $query, string $categorySlug): Builder
    {
        return $query->whereHas('category', function ($q) use ($categorySlug) {
            $q->where('slug', $categorySlug);
        });
    }

    /**
     * Scope: Filter events by subcategory slug.
     *
     * @param Builder $query
     * @param string $subcategorySlug
     * @return Builder
     */
    public function scopeSubcategory(Builder $query, string $subcategorySlug): Builder
    {
        return $query->whereHas('subcategory', function ($q) use ($subcategorySlug) {
            $q->where('slug', $subcategorySlug);
        });
    }

    /**
     * Scope: Only published events.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope: Only featured events.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: Exclude cancelled events.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('is_cancelled', false);
    }

    /**
     * Scope: Find by slug.
     *
     * @param Builder $query
     * @param string $slug
     * @return Builder
     */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    /**
     * Increment view count.
     *
     * @return void
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * Generate slug from title.
     *
     * @return void
     */
    public function generateSlug(): void
    {
        $this->slug = \Illuminate\Support\Str::slug($this->title);
    }

    /**
     * Scope: Select with latitude and longitude extracted from PostGIS or SQLite columns.
     * This adds lat/lng as separate columns for API responses.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithCoordinates(Builder $query): Builder
    {
        if (DB::getDriverName() === 'pgsql') {
            return $query->addSelect([
                DB::raw('ST_Y(location::geometry) as latitude'),
                DB::raw('ST_X(location::geometry) as longitude'),
            ]);
        } else {
            // SQLite and other databases - use latitude and longitude columns
            return $query->addSelect([
                'latitude',
                'longitude',
            ]);
        }
    }

    /**
     * Scope: Select with distance from a point.
     * Uses ST_Distance for PostgreSQL, Haversine formula for SQLite.
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
     * Set the location using latitude and longitude.
     * Converts to PostGIS GEOGRAPHY point for PostgreSQL, updates lat/lng columns for SQLite.
     *
     * @param float $latitude
     * @param float $longitude
     * @return void
     */
    public function setLocationFromCoordinates(float $latitude, float $longitude): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "UPDATE events SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?",
                [$longitude, $latitude, $this->id]
            );
        } else {
            // SQLite - update latitude and longitude columns
            DB::statement(
                "UPDATE events SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?",
                [$longitude, $latitude, $this->id]
            );
        }
    }
}
