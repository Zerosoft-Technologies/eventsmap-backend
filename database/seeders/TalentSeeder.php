<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TalentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $talents = [
            [
                'name' => 'DJ Shadow',
                'slug' => 'dj-shadow',
                'image' => 'https://picsum.photos/seed/djshadow/400/400.jpg',
                'bio' => 'World-renowned DJ and producer known for groundbreaking electronic music and innovative turntablism.',
                'social_links' => json_encode([
                    'spotify' => 'https://spotify.com/artist/djshadow',
                    'instagram' => 'https://instagram.com/djshadow',
                    'website' => 'https://djshadow.com'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'The Midnight',
                'slug' => 'the-midnight',
                'image' => 'https://picsum.photos/seed/themidnight/400/400.jpg',
                'bio' => 'Synthwave duo from Los Angeles bringing nostalgic 80s vibes to modern audiences.',
                'social_links' => json_encode([
                    'spotify' => 'https://spotify.com/artist/themidnight',
                    'instagram' => 'https://instagram.com/themidnight',
                    'website' => null
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Armin van Buuren',
                'slug' => 'armin-van-buuren',
                'image' => 'https://picsum.photos/seed/armin/400/400.jpg',
                'bio' => 'Dutch DJ and record producer, voted #1 DJ in the world a record 5 times.',
                'social_links' => json_encode([
                    'spotify' => 'https://spotify.com/artist/arminvanbuuren',
                    'instagram' => 'https://instagram.com/arminvanbuuren',
                    'website' => 'https://arminvanbuuren.com'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Deadmau5',
                'slug' => 'deadmau5',
                'image' => 'https://picsum.photos/seed/deadmau5/400/400.jpg',
                'bio' => 'Canadian electronic music producer known for his progressive house and electro house tracks.',
                'social_links' => json_encode([
                    'spotify' => 'https://spotify.com/artist/deadmau5',
                    'instagram' => 'https://instagram.com/deadmau5',
                    'website' => 'https://deadmau5.com'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Carly Rae Jepsen',
                'slug' => 'carly-rae-jepsen',
                'image' => 'https://picsum.photos/seed/carlyrae/400/400.jpg',
                'bio' => 'Canadian singer-songwriter known for her catchy pop hits and dedicated fanbase.',
                'social_links' => json_encode([
                    'spotify' => 'https://spotify.com/artist/carlyraejepsen',
                    'instagram' => 'https://instagram.com/carlyraejepsen',
                    'website' => 'https://carlyraejepsen.com'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'The Weeknd',
                'slug' => 'the-weeknd',
                'image' => 'https://picsum.photos/seed/theweeknd/400/400.jpg',
                'bio' => 'Canadian singer-songwriter and record producer known for his dark R&B music.',
                'social_links' => json_encode([
                    'spotify' => 'https://spotify.com/artist/theweeknd',
                    'instagram' => 'https://instagram.com/theweeknd',
                    'website' => 'https://theweeknd.com'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Martin Garrix',
                'slug' => 'martin-garrix',
                'image' => 'https://picsum.photos/seed/martingarrix/400/400.jpg',
                'bio' => 'Dutch DJ and producer known for his viral hit "Animals" and festival performances.',
                'social_links' => json_encode([
                    'spotify' => 'https://spotify.com/artist/martingarrix',
                    'instagram' => 'https://instagram.com/martingarrix',
                    'website' => 'https://martingarrix.com'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Dua Lipa',
                'slug' => 'dua-lipa',
                'image' => 'https://picsum.photos/seed/dualipa/400/400.jpg',
                'bio' => 'English singer-songwriter known for her disco-pop hits and powerful vocals.',
                'social_links' => json_encode([
                    'spotify' => 'https://spotify.com/artist/dualipa',
                    'instagram' => 'https://instagram.com/dualipa',
                    'website' => 'https://dualipa.com'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'David Guetta',
                'slug' => 'david-guetta',
                'image' => 'https://picsum.photos/seed/davidguetta/400/400.jpg',
                'bio' => 'French DJ and producer who pioneered EDM and brought electronic music to the mainstream.',
                'social_links' => json_encode([
                    'spotify' => 'https://spotify.com/artist/davidguetta',
                    'instagram' => 'https://instagram.com/davidguetta',
                    'website' => 'https://davidguetta.com'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Billie Eilish',
                'slug' => 'billie-eilish',
                'image' => 'https://picsum.photos/seed/billieeilish/400/400.jpg',
                'bio' => 'American singer-songwriter known for her unique sound and visual aesthetic.',
                'social_links' => json_encode([
                    'spotify' => 'https://spotify.com/artist/billieeilish',
                    'instagram' => 'https://instagram.com/billieeilish',
                    'website' => 'https://billieeilish.com'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('talents')->insert($talents);
    }
}
