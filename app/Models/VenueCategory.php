<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VenueCategory extends Model
{
    use HasFactory;

    protected $table = 'venue_categories';

    /**
     * Canonical slug for the single top-level venue profile category (see {@see \Database\Seeders\VenueCategorySeeder}).
     */
    public const MAIN_SLUG = 'venue';

    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (VenueCategory $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(VenueSubcategory::class, 'venue_category_id');
    }
}
