<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Haversine / bbox helpers for V2 models with latitude & longitude columns.
 */
trait HasV2GeoScopes
{
    protected static function haversineCosArgExpression(): string
    {
        return 'cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))';
    }

    protected static function clampAcosArgSql(): string
    {
        $e = self::haversineCosArgExpression();

        return "CASE WHEN ($e) < -1.0 THEN -1.0 WHEN ($e) > 1.0 THEN 1.0 ELSE ($e) END";
    }

    protected static function haversineDistanceKmSql(): string
    {
        $cosArg = self::clampAcosArgSql();

        return "(6371 * acos($cosArg))";
    }

    /**
     * Bindings for one Haversine expression (cos-arg appears three times in CASE clamp).
     *
     * @return list<float>
     */
    protected static function haversineDistanceKmBindings(float $lat, float $lng): array
    {
        return [$lat, $lng, $lat, $lat, $lng, $lat, $lat, $lng, $lat];
    }

    public function scopeWithinBbox(Builder $query, float $minLat, float $maxLat, float $minLng, float $maxLng): Builder
    {
        return $query->whereBetween('latitude', [$minLat, $maxLat])
            ->whereBetween('longitude', [$minLng, $maxLng]);
    }

    public function scopeWithinRadius(Builder $query, float $lat, float $lng, float $radiusKm): Builder
    {
        $haversine = self::haversineDistanceKmSql();
        $bindings = array_merge(self::haversineDistanceKmBindings($lat, $lng), [$radiusKm]);

        return $query->whereRaw("$haversine <= ?", $bindings);
    }

    public function scopeWithDistance(Builder $query, float $lat, float $lng): Builder
    {
        $haversine = self::haversineDistanceKmSql();

        return $query->selectRaw("*, $haversine as distance_km", self::haversineDistanceKmBindings($lat, $lng));
    }
}
