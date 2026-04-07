<?php

namespace Database\Seeders;

use App\Models\OrganiserCategory;
use App\Models\OrganiserSubcategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrganiserCategorySeeder2 extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Promoter' => [
                'Concert promoter',
                'Club promoter',
                'Festival promoter',
                'Tour promoter',
            ],
            'Event Producer' => [
                'Technical production',
                'Creative production',
                'Full-service production',
            ],
            'Venue Organiser' => [
                'Nightclub',
                'Theatre',
                'Concert venue',
                'Cinema',
            ],
            'Artist / Collective' => [
                'DJ / DJ collective',
                'Band / live act',
                'Dance company',
                'Theatre group',
            ],
            'Festival Organisation' => [
                'Music festival',
                'Dance festival',
                'Film festival',
                'Theatre festival',
            ],
            'Cultural Organisation' => [
                'City / municipality',
                'Cultural center',
                'Museum / institute',
            ],
            'Brand / Commercial' => [
                'Brand activation',
                'Corporate event',
                'Sponsored event',
            ],
            'Community Organiser' => [
                'Open mic organiser',
                'Local organiser',
                'Non-profit organiser',
            ],
            'Nightlife Event Brand' => [
                'Club night',
                'Label night',
                'Underground promoter',
            ],
        ];

        foreach ($categories as $categoryName => $subcategories) {
            $category = OrganiserCategory::updateOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName]
            );

            foreach ($subcategories as $subcategoryName) {
                OrganiserSubcategory::updateOrCreate(
                    [
                        'organiser_category_id' => $category->id,
                        'slug' => Str::slug($subcategoryName),
                    ],
                    ['name' => $subcategoryName]
                );
            }
        }
    }
}
