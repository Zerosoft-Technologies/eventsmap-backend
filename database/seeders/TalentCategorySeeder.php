<?php

namespace Database\Seeders;

use App\Models\TalentCategory;
use App\Models\TalentSubcategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TalentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Singer' => [
                'Pop',
                'Rock',
                'Jazz',
                'Classical / Opera',
                'R&B / Soul',
                'Electronic / EDM',
                'Hip-hop / Rap',
                'World / Folk',
            ],
            'Musician' => [
                'Acoustic guitar',
                'Electric guitar',
                'Classical guitar',
                'Flamenco guitar',
                'Electric bass',
                'Double bass',
                'Drum kit',
                'Percussion',
                'Piano',
                'Keyboard',
                'Synth',
                'Violin',
                'Cello',
                'Saxophone',
                'Trumpet',
                'Flute',
                'DJ',
                'Live electronic',
                'Band / Group',
            ],
            'Dancer' => [
                'Ballet',
                'Contemporary',
                'Hip-hop / Street',
                'Latin',
                'Ballroom',
                'Breakdance',
                'Jazz',
                'Cultural',
            ],
            'Actor' => [
                'Film actor',
                'Theatre actor',
                'Television actor',
                'Voice actor',
            ],
            'Group' => [
                'Music Band',
                'Music Ensemble',
                'Dance Company',
                'Theatre Company',
                'Film',
            ],
        ];

        foreach ($categories as $categoryName => $subcategories) {
            $category = TalentCategory::updateOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName]
            );

            foreach ($subcategories as $subcategoryName) {
                TalentSubcategory::updateOrCreate(
                    [
                        'talent_category_id' => $category->id,
                        'slug' => Str::slug($subcategoryName),
                    ],
                    ['name' => $subcategoryName]
                );
            }
        }
    }
}
