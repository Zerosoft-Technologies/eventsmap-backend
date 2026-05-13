<?php

namespace Database\Seeders;

use App\Models\VenueCategory;
use App\Models\VenueSubcategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Venue profile taxonomy (dedicated tables, same API shape as talents / organisers).
 *
 * Parent category is always "Venue"; subcategory names match the Venue block in {@see SubcategoriesSeeder}.
 */
class VenueCategorySeeder extends Seeder
{
    /**
     * @see SubcategoriesSeeder (category id 8 — Venue) — keep names aligned for product consistency.
     */
    private const VENUE_SUBCATEGORY_NAMES = [
        'Bar',
        'Casino',
        'Cinema',
        'Concert Hall',
        'Dance Venue',
        'Event Hall',
        'Government Venue',
        'Hotel',
        'Nightclub',
        'Open-air Venue',
        'Festival Grounds',
        'Restaurant',
        'Stadium',
        'Theatre',
        'Other',
    ];

    public function run(): void
    {
        $category = VenueCategory::query()->updateOrCreate(
            ['slug' => VenueCategory::MAIN_SLUG],
            ['name' => 'Venue']
        );

        foreach (self::VENUE_SUBCATEGORY_NAMES as $name) {
            VenueSubcategory::query()->updateOrCreate(
                [
                    'venue_category_id' => $category->id,
                    'slug' => Str::slug($name),
                ],
                ['name' => $name]
            );
        }
    }
}
