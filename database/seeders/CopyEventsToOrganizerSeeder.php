<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CopyEventsToOrganizerSeeder extends Seeder
{
    /**
     * Copy all events from events table to events_organizer table.
     * This seeder reads the same data structure as EventsSeeder and saves it to events_organizer.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Include the same events data as EventsSeeder
        // You can copy the entire $events array from EventsSeeder.php here
        // For demonstration, I'll show a few events and the pattern

        $events = [
        // 🎭 TALENT 类别 (10个事件)
        [
            'title' => 'Broadway Star Showcase',
            'description' => 'An exclusive evening featuring Tony Award-winning actors and actresses performing monologues from classic Broadway plays.',
            'category' => 'Talent', // Using string category for events_organizer
            'price' => 65.00,
            'dresscode' => 'evening elegant',
            'min_age' => 16,
            'start_datetime' => $now->copy()->addDays(3)->setHour(19),
            'end_datetime' => $now->copy()->addDays(3)->setHour(22),
            'city' => 'New York',
            'address' => 'Broadway Theater District, New York, NY',
            'location_name' => 'Broadway Theater District',
            'latitude' => 40.7614,
            'longitude' => -73.9834,
            'morning' => false,
            'afternoon' => false,
            'evening' => true,
            'night' => false,
        ],
        [
            'title' => 'International Celebrity Gala',
            'description' => 'Red carpet event with A-list celebrities for charity fundraising with live performances.',
            'category' => 'Talent',
            'price' => 250.00,
            'dresscode' => 'black tie',
            'min_age' => 21,
            'start_datetime' => $now->copy()->addDays(10)->setHour(20),
            'end_datetime' => $now->copy()->addDays(10)->setHour(23),
            'city' => 'Los Angeles',
            'address' => 'SoFi Stadium, Los Angeles, CA',
            'location_name' => 'SoFi Stadium',
            'latitude' => 33.9535,
            'longitude' => -118.3390,
            'morning' => false,
            'afternoon' => false,
            'evening' => true,
            'night' => false,
        ],
        [
            'title' => 'Historic Theatre Restoration Gala',
            'description' => 'Fundraiser for the restoration of a historic theatre venue.',
            'category' => 'Venue',
            'price' => 100.00,
            'dresscode' => 'formal',
            'min_age' => 21,
            'start_datetime' => $now->copy()->addDays(14)->setHour(18),
            'end_datetime' => $now->copy()->addDays(14)->setHour(23),
            'city' => 'Paris',
            'address' => 'Palais Garnier, Paris, France',
            'location_name' => 'Palais Garnier',
            'latitude' => 48.8719,
            'longitude' => 2.3316,
            'morning' => false,
            'afternoon' => false,
            'evening' => true,
            'night' => false,
        ],
        [
            'title' => 'After Hours Jazz Club',
            'description' => 'Late-night jazz session in intimate club setting.',
            'category' => 'Nightlife',
            'price' => 30.00,
            'dresscode' => 'smart casual',
            'min_age' => 21,
            'start_datetime' => $now->copy()->addDays(2)->setHour(1),
            'end_datetime' => $now->copy()->addDays(2)->setHour(4),
            'city' => 'New Orleans',
            'address' => 'Frenchmen Street, New Orleans, LA',
            'location_name' => 'Frenchmen Street Jazz Club',
            'latitude' => 29.9631,
            'longitude' => -90.0579,
            'morning' => false,
            'afternoon' => false,
            'evening' => false,
            'night' => true,
        ],
        // Add more events by copying from EventsSeeder.php...
        ];

        // Insert events into events_organizer table
        foreach ($events as $eventData) {
            $latitude = $eventData['latitude'];
            $longitude = $eventData['longitude'];
            
            // Prepare event data for events_organizer table
            $eventInsertData = [
                'title' => $eventData['title'],
                'slug' => Str::slug($eventData['title']) . '-' . uniqid(),
                'status' => 'published',
                'description' => $eventData['description'],
                'category' => $eventData['category'], // String field for events_organizer
                'price' => $eventData['price'],
                'dresscode' => $eventData['dresscode'],
                'min_age' => $eventData['min_age'],
                'start_datetime' => $eventData['start_datetime']->toDateTimeString(),
                'end_datetime' => $eventData['end_datetime']->toDateTimeString(),
                'timezone' => 'UTC',
                'city' => $eventData['city'],
                'address' => $eventData['address'],
                'location_name' => $eventData['location_name'] ?? 'Default Location',
                'country' => $this->getCountryFromCity($eventData['city']),
                'is_published' => true,
                'is_featured' => false,
                'is_cancelled' => false,
                'is_archived' => false,
                'view_count' => rand(50, 500),
                'morning' => $eventData['morning'] ?? false,
                'afternoon' => $eventData['afternoon'] ?? false,
                'evening' => $eventData['evening'] ?? false,
                'night' => $eventData['night'] ?? false,
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ];

            // Insert event with location data based on database type
            if (DB::getDriverName() === 'pgsql') {
                // PostgreSQL with PostGIS
                DB::table('events_organizer')->insert(array_merge($eventInsertData, [
                    'location' => DB::raw("ST_SetSRID(ST_MakePoint({$longitude}, {$latitude}), 4326)::geography"),
                ]));
            } else {
                // SQLite and other databases - use latitude and longitude columns
                DB::table('events_organizer')->insert(array_merge($eventInsertData, [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]));
            }
        }

        $this->command->info('Copied ' . count($events) . ' events to events_organizer table.');
        $this->command->info('To copy ALL events, copy the complete $events array from EventsSeeder.php');
    }

    /**
     * Helper method to determine country from city
     */
    private function getCountryFromCity(string $city): string
    {
        $cityCountryMap = [
            'New York' => 'USA',
            'Los Angeles' => 'USA',
            'San Francisco' => 'USA',
            'Chicago' => 'USA',
            'Miami' => 'USA',
            'Las Vegas' => 'USA',
            'Nashville' => 'USA',
            'Seattle' => 'USA',
            'Boston' => 'USA',
            'New Orleans' => 'USA',
            'Houston' => 'USA',
            'Phoenix' => 'USA',
            'Paris' => 'France',
            'London' => 'UK',
            'Berlin' => 'Germany',
            'Amsterdam' => 'Netherlands',
            'Dublin' => 'Ireland',
            'Mumbai' => 'India',
            'Buenos Aires' => 'Argentina',
            'Seville' => 'Spain',
        ];

        return $cityCountryMap[$city] ?? 'USA';
    }
}
