<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubcategoriesSeeder extends Seeder
{
    /**
     * STRICT subcategory seeding:
     * - Parent reference by fixed category_id (from CategoriesSeeder)
     * - Uniqueness key: (category_id, name) via DB UNIQUE constraint
     * - Insert/update via bulk upsert for performance
     * - Stable display_order within each category
     */
    public function run(): void
    {
        $now = now();

        // Category IDs are fixed by CategoriesSeeder.
        $map = [
            1 => [ // Music
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
            2 => [ // Dance
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
            3 => [ // Theatre
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
            4 => [ // Community
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
            5 => [ // Film
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
            6 => [ // Nightlife
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
            7 => [ // Talent
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
            8 => [ // Venue
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
            9 => [ // Organiser
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
            ],
        ];

        $rows = [];
        foreach ($map as $categoryId => $names) {
            $order = 1;
            foreach ($names as $name) {
                $rows[] = [
                    'category_id' => $categoryId,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => null,
                    'display_order' => $order++,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($rows): void {
            // Requires DB UNIQUE(category_id, name)
            DB::table('subcategories')->upsert(
                $rows,
                ['category_id', 'name'],
                ['slug', 'description', 'display_order', 'is_active', 'updated_at']
            );

            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT setval(pg_get_serial_sequence('subcategories','id'), (SELECT MAX(id) FROM subcategories))");
            }
        });
    }
}

