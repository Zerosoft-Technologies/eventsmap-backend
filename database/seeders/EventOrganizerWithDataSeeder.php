<?php

namespace Database\Seeders;

use App\Models\EventOrganizer;
use App\Models\Talent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventOrganizerWithDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test events
        $events = [
            [
                'title' => 'Summer Music Festival',
                'slug' => 'summer-music-festival-' . uniqid(),
                'status' => 'published',
                'description' => 'An amazing outdoor music festival featuring top artists',
                'short_description' => 'Outdoor music festival',
                'category' => 'Music',
                'price' => 75.00,
                'currency' => 'USD',
                'start_datetime' => now()->addDays(30),
                'end_datetime' => now()->addDays(30)->addHours(8),
                'timezone' => 'UTC',
                'city' => 'Los Angeles',
                'country' => 'USA',
                'is_published' => true,
                'is_featured' => true,
                'is_cancelled' => false,
                'is_archived' => false,
                'view_count' => 500,
            ],
            [
                'title' => 'Tech Conference 2026',
                'slug' => 'tech-conference-2026-' . uniqid(),
                'status' => 'published',
                'description' => 'Annual technology conference with industry leaders',
                'short_description' => 'Tech conference',
                'category' => 'Technology',
                'price' => 299.00,
                'currency' => 'USD',
                'start_datetime' => now()->addDays(45),
                'end_datetime' => now()->addDays(46),
                'timezone' => 'UTC',
                'city' => 'San Francisco',
                'country' => 'USA',
                'is_published' => true,
                'is_featured' => false,
                'is_cancelled' => false,
                'is_archived' => false,
                'view_count' => 250,
            ],
            [
                'title' => 'Food & Wine Expo',
                'slug' => 'food-wine-expo-' . uniqid(),
                'status' => 'draft',
                'description' => 'Exhibition of fine foods and wines from around the world',
                'short_description' => 'Food and wine exhibition',
                'category' => 'Food',
                'price' => 50.00,
                'currency' => 'USD',
                'start_datetime' => now()->addDays(60),
                'end_datetime' => now()->addDays(60)->addHours(6),
                'timezone' => 'UTC',
                'city' => 'New York',
                'country' => 'USA',
                'is_published' => false,
                'is_featured' => false,
                'is_cancelled' => false,
                'is_archived' => false,
                'view_count' => 100,
            ],
        ];

        $createdEvents = [];
        foreach ($events as $eventData) {
            $event = EventOrganizer::create($eventData);
            $createdEvents[] = $event;
            $this->command->info("Created event: {$event->title}");
        }

        // Get some talents
        $talents = Talent::take(5)->get();
        
        if ($talents->isNotEmpty()) {
            // Attach talents to events
            foreach ($createdEvents as $index => $event) {
                // Attach 2-3 random talents to each event
                $eventTalents = $talents->random(rand(2, 3));
                
                foreach ($eventTalents as $sortOrder => $talent) {
                    $event->talents()->attach($talent->id, [
                        'role' => ['Performer', 'Speaker', 'Headliner', 'Guest', 'Presenter'][$sortOrder],
                        'sort_order' => $sortOrder,
                    ]);
                }
                
                $this->command->info("Attached talents to: {$event->title}");
            }
        }
    }
}
