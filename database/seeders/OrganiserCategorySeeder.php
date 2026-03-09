<?php

namespace Database\Seeders;

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

        // Insert or fetch the "Organiser" category
        $category = DB::table('categories')->where('slug', 'organiser')->first();

        if (!$category) {
            $categoryId = DB::table('categories')->insertGetId([
                'name'          => 'Organiser',
                'slug'          => 'organiser',
                'description'   => null,
                'icon'          => null,
                'color'         => null,
                'display_order' => 0,
                'is_active'     => true,
                'is_featured'   => false,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        } else {
            $categoryId = $category->id;
        }

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

            // Prevent duplicates: check by category_id + slug
            $exists = DB::table('subcategories')
                ->where('category_id', $categoryId)
                ->where('slug', $slug)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('subcategories')->insert([
                'category_id'   => $categoryId,
                'name'          => $name,
                'slug'          => $slug,
                'description'   => null,
                'display_order' => 0,
                'is_active'     => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }
}

