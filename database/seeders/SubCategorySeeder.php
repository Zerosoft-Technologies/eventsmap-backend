<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubCategorySeeder extends Seeder
{
    /**
     * Seed predefined subcategories for predefined categories.
     *
     * Idempotent by design:
     * - Uniqueness key: (category_id, name)
     * - Slug is normalized consistently via Str::slug(name)
     */
    public function run(): void
    {
        $categoriesData = [
            'talent' => [
                'Actor',
                'Actress',
                'Celebrity',
                'Circus Artist',
                'Comic',
                'Dancer',
                'DJ/VJ',
                'Entertainer',
                'Magician/Illusionist',
                'Musician',
                'Performing Animal',
                'Player',
                'Puppeteer',
                'Singer',
                'Other',
            ],
            'venue' => [
                'Bar',
                'Casino',
                'Cinema',
                'Dancing',
                'Government',
                'Hall',
                'Hotel',
                'Night Club',
                'Open Air',
                'Restaurant',
                'Stadium',
                'Theatre',
                'Other',
            ],
            'nightlife' => [
                'After Midnight Bar',
                'After Midnight Restaurant',
                'After Midnight Show',
                'After Party',
                'Cinema',
                'Dance',
                'Dancing',
                'Dinner Show',
                'DJ/VJ Session',
                'Karaoke',
                'Ladies Night',
                'Late Night Parties',
                'Live Music',
                'Live Music Bar',
                'Nightclub',
                'Parties',
                'Quiz, Bingo, Blind Test',
                'Slots & Gambling',
                'Theatre',
                'Other',
            ],
            'film' => [
                'Action',
                'Adventure',
                'Adult',
                'Animation',
                'Biographical',
                'Children',
                'Comedy',
                'Crime',
                'Detective',
                'Drama',
                'Educational',
                'Fantasy',
                'Horror',
                'Historical',
                'Musical',
                'Mystery',
                'Romance',
                'Science Fiction',
                'Short',
                'Sports',
                'Thriller',
                'War',
                'Western',
                'Other',
            ],
            'theatre' => [
                'Acting',
                'Cabaret',
                'Children',
                'Circus',
                'Comedy',
                'Drama',
                'Experimental',
                'Farce',
                'Immersive',
                'Improvisational',
                'Magic & Illusion',
                'Melodrama',
                'Mime',
                'Musical',
                'Of The Absurd',
                'Open Stage',
                'Opera',
                'Performance',
                'Play',
                'Play With Music',
                'Puppetry',
                'Revue',
                'Rock Opera',
                'Show',
                'Stand Up Comedy',
                'Storytelling',
                'Tragedy',
                'Variety Show',
                'Other',
            ],
            'dance' => [
                'Ballet',
                'Ballroom',
                'Belly Dance',
                'Bharatanatyam',
                'Break',
                'Can-Can',
                'Cha-Cha',
                'Children',
                'Classical',
                'Contemporary',
                'Country',
                'Folk',
                'Highland',
                'Hip Hop',
                'Irish',
                'Jazz',
                'Kathak',
                'Modern',
                'Pole',
                'Rumba',
                'Salsa',
                'Street',
                'Swing',
                'Tap',
                'Other',
            ],
            'community' => [
                'Carnival',
                'Children',
                'Christmas Market',
                'Fair',
                'Festival',
                'Field Day',
                'Food & Drink',
                'Light Show',
                'Local Culture',
                'Neighbourhood',
                'Oktoberfest',
                'Sports',
                'Street Event',
                'World Music Day',
                'Other',
            ],
            'music' => [
                'Alternative',
                'Ambient',
                'Blues',
                'Children',
                'Classic Pop',
                'Concert',
                'Contemporary',
                'Country',
                'Disco',
                'DJ/VJ',
                'Drum & Bass',
                'EDM Electronic Dance Music',
                'Folk',
                'Funk',
                'Garage',
                'Hip Hop',
                'House',
                'Indie',
                'Jazz',
                'Jungle',
                'Latin',
                'Latin Bachata',
                'Latin Boogie',
                'Latin Cha-Cha',
                'Latin Cumbia',
                'Latin Dembow',
                'Latin Guaguanco',
                'Latin Reggaeton',
                'Latin Salsa',
                'Latin Son',
                'Latin Merengue',
                'Live Music',
                'Metal',
                'Opera',
                'Orchestra',
                'Pop',
                'R&B',
                'Reggae',
                'Sacred',
                'Singing Choral',
                'Soul',
                'Street',
                'Techno',
                'Other',
            ],
        ];

        $categoriesBySlug = Category::query()
            ->whereIn('slug', array_keys($categoriesData))
            ->get(['id', 'slug'])
            ->keyBy('slug');

        DB::transaction(function () use ($categoriesData, $categoriesBySlug): void {
            $now = now();

            foreach ($categoriesData as $categorySlug => $subNames) {
                $category = $categoriesBySlug->get($categorySlug);
                if (!$category) {
                    continue;
                }

                foreach ($subNames as $name) {
                    SubCategory::updateOrCreate(
                        [
                            'category_id' => $category->id,
                            'name' => $name,
                        ],
                        [
                            'slug' => Str::slug($name),
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );
                }
            }
        });

        $this->command?->info('Seeded subcategories (idempotent by category_id + name).');
    }
}

