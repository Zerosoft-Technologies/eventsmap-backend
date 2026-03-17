<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class OrganiserCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $category = Category::updateOrCreate(
            ['slug' => 'organiser'],
            [
                'name' => 'Organiser',
                'description' => null,
                'icon' => null,
                'color' => null,
                'display_order' => 0,
                'is_active' => true,
                'is_featured' => false,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        // Define organiser subcategories
        $names = [
            'Event Planner',
            'Wedding Planner',
            'Corporate Event Organiser',
            'Festival Organiser',
            'Party Organiser',
            'Exhibition Organiser',
            'Conference Organiser',
            'Community Event Organiser',
            'Sports Event Organiser',
            'Other',
        ];

        foreach ($names as $name) {
            $slug = Str::slug($name);
            SubCategory::updateOrCreate(
                [
                    'category_id' => $category->id,
                    'name' => $name,
                ],
                [
                    'slug' => $slug,
                    'description' => null,
                    'display_order' => 0,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}

