<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Event extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'category_id',
        'subcategory_id',
        'price',
        'dresscode',
        'min_age',
        'start_datetime',
        'end_datetime',
        'city',
        'address',
        'is_published',
        'morning',
        'afternoon',
        'evening',
        'night',
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
            'min_age' => 'integer',
            'is_published' => 'boolean',
            'category_id' => 'integer',
            'subcategory_id' => 'integer',
            'morning' => 'boolean',
            'afternoon' => 'boolean',
            'evening' => 'boolean',
            'night' => 'boolean',
        ];
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['is_live_now', 'latitude', 'longitude'];

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
     * Uses ST_DWithin for efficient spatial indexing.
     *
     * @param Builder $query
     * @param float $latitude
     * @param float $longitude
     * @param float $radiusKm Radius in kilometers
     * @return Builder
     */
    public function scopeWithinRadius(Builder $query, float $latitude, float $longitude, float $radiusKm): Builder
    {
        $radiusMeters = $radiusKm * 1000;

        return $query->whereRaw(
            'ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
            [$longitude, $latitude, $radiusMeters]
        );
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
     * Scope: Select with latitude and longitude extracted from PostGIS.
     * This adds lat/lng as separate columns for API responses.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithCoordinates(Builder $query): Builder
    {
        return $query->addSelect([
            DB::raw('ST_Y(location::geometry) as latitude'),
            DB::raw('ST_X(location::geometry) as longitude'),
        ]);
    }

    /**
     * Scope: Select with distance from a point.
     *
     * @param Builder $query
     * @param float $latitude
     * @param float $longitude
     * @return Builder
     */
    public function scopeWithDistanceFrom(Builder $query, float $latitude, float $longitude): Builder
    {
        return $query->addSelect([
            DB::raw("ST_Distance(location, ST_SetSRID(ST_MakePoint({$longitude}, {$latitude}), 4326)::geography) as distance_meters"),
        ]);
    }

    /**
     * Set the location using latitude and longitude.
     * Converts to PostGIS GEOGRAPHY point.
     *
     * @param float $latitude
     * @param float $longitude
     * @return void
     */
    public function setLocationFromCoordinates(float $latitude, float $longitude): void
    {
        DB::statement(
            "UPDATE events SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?",
            [$longitude, $latitude, $this->id]
        );
    }
}
