<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Seed predefined categories.
     *
     * Subcategories are seeded by `SubCategorySeeder` (idempotent).
     */
    public function run(): void
    {
        // Predefined categories
        $categoriesData = [
            "talent" => [
                "Talent",
            ],
            "venue" => [
                "Venue",
            ],
            "nightlife" => [
                "Nightlife",
            ],
            "film" => [
                "Film",
            ],
            "theatre" => [
                "Theatre",
            ],
            "dance" => [
                "Dance",
            ],
            "community" => [
                "Community",
            ],
            "music" => [
                "Music",
            ]
        ];

        // Use transaction for consistency
        DB::transaction(function () use ($categoriesData) {
            foreach ($categoriesData as $categorySlug => $categoryMeta) {
                // Create or find category
                $category = Category::firstOrCreate(
                    ['slug' => strtolower($categorySlug)],
                    ['name' => $categoryMeta[0] ?? ucfirst($categorySlug)]
                );
            }
        });

        $this->command->info('Seeded ' . count($categoriesData) . ' categories with their subcategories.');
    }
}
