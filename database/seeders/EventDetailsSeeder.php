<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class EventDetailsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks temporarily
        Schema::disableForeignKeyConstraints();

        // Get existing events
        $events = DB::table('events')->limit(20)->get();

        foreach ($events as $event) {
            // Generate slug if not exists
            if (!$event->slug) {
                $slug = \Illuminate\Support\Str::slug($event->title);
                DB::table('events')->where('id', $event->id)->update(['slug' => $slug]);
            }

            // Update event with extended fields
            $updateData = [
                'slug' => $event->slug ?? \Illuminate\Support\Str::slug($event->title),
                'min_price' => $event->price * 0.5,
                'max_price' => $event->price * 2,
                'currency' => 'USD',
                'timezone' => 'America/New_York',
                'venue_name' => $this->getRandomVenue(),
                'country' => 'USA',
                'max_age' => null,
                'organizer_name' => $this->getRandomOrganizer(),
                'contact_info' => json_encode([
                    'email' => 'info@' . str_replace(' ', '', strtolower($this->getRandomOrganizer())) . '.com',
                    'phone' => '+1-555-' . rand(100, 999) . '-' . rand(1000, 9999),
                    'website' => 'https://' . str_replace(' ', '', strtolower($this->getRandomOrganizer())) . '.com'
                ]),
                'cover_image' => 'https://picsum.photos/seed/event' . $event->id . '/1200/600.jpg',
                'video_url' => 'https://youtube.com/watch?v=' . substr(md5($event->id), 0, 11),
                'about' => json_encode($this->getRandomAbout()),
                'location_details' => json_encode($this->getRandomLocationDetails($event->city, $event->address)),
                'booking' => json_encode($this->getRandomBooking()),
                'social_links' => json_encode($this->getRandomSocialLinks()),
                'is_featured' => rand(0, 10) > 7,
                'is_cancelled' => false,
                'meta_title' => $event->title . ' | ' . $event->city,
                'meta_description' => 'Join us for an amazing ' . strtolower($event->title) . ' in ' . $event->city . '. Book your tickets now!',
                'tags' => json_encode($this->getRandomTags()),
                'view_count' => rand(100, 5000),
                'updated_at' => now(),
            ];

            DB::table('events')->where('id', $event->id)->update($updateData);

            // Add images for the event
            $this->seedEventImages($event->id);

            // Add talents to the event
            $this->seedEventTalents($event->id);
        }

        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Seed images for an event
     */
    private function seedEventImages($eventId): void
    {
        $images = [];
        $imageCount = rand(3, 8);

        for ($i = 0; $i < $imageCount; $i++) {
            $images[] = [
                'event_id' => $eventId,
                'url' => 'https://picsum.photos/seed/event' . $eventId . 'img' . $i . '/800/600.jpg',
                'alt_text' => 'Event image ' . ($i + 1),
                'caption' => $i === 0 ? 'Main event photo' : null,
                'is_primary' => $i === 0,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('event_images')->insert($images);
    }

    /**
     * Seed talents for an event
     */
    private function seedEventTalents($eventId): void
    {
        $talentIds = DB::table('talents')->pluck('id')->toArray();
        $talentCount = rand(2, 5);
        $selectedTalents = array_rand($talentIds, min($talentCount, count($talentIds)));
        
        if (!is_array($selectedTalents)) {
            $selectedTalents = [$selectedTalents];
        }

        $roles = ['Headliner', 'Supporting Act', 'Opening Act', 'Special Guest', 'DJ Set'];
        
        foreach ($selectedTalents as $index => $talentIndex) {
            DB::table('event_talent')->insert([
                'event_id' => $eventId,
                'talent_id' => $talentIds[$talentIndex],
                'role' => $roles[$index] ?? 'Performer',
                'sort_order' => $index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Get random venue name
     */
    private function getRandomVenue(): string
    {
        $venues = [
            'Madison Square Garden',
            'Central Park Amphitheater',
            'Brooklyn Bowl',
            'Terminal 5',
            'Webster Hall',
            'Radio City Music Hall',
            'Barclays Center',
            'Beacon Theatre',
            'Apollo Theater',
            'Hammerstein Ballroom',
        ];

        return $venues[array_rand($venues)];
    }

    /**
     * Get random organizer name
     */
    private function getRandomOrganizer(): string
    {
        $organizers = [
            'Live Nation Entertainment',
            'AEG Presents',
            'Event Productions Inc.',
            'NYC Concert Promotions',
            'Madhouse Events',
            'Electric Zoo Productions',
            'Summer Stage NYC',
            'Brooklyn Events Co.',
            'Manhattan Music Group',
            'Big Apple Productions',
        ];

        return $organizers[array_rand($organizers)];
    }

    /**
     * Get random about data
     */
    private function getRandomAbout(): array
    {
        return [
            'accessibility' => [
                'wheelchair_accessible' => (bool) rand(0, 1),
            ],
            'planning' => [
                'ticket_required' => true,
            ],
            'services' => [
                'wifi' => (bool) rand(0, 1),
            ],
            'amenities' => [
                'bar' => true,
                'food_court' => (bool) rand(0, 1),
                'merchandise' => true,
                'vip_lounge' => (bool) rand(0, 1),
            ],
            'children' => [
                'suitable_for_children' => (bool) rand(0, 1),
            ],
            'description' => 'Join us for an unforgettable experience featuring world-class entertainment, amazing atmosphere, and memories that will last a lifetime.',
            'rules' => [
                'No outside food or beverages',
                'No professional cameras without permit',
                'All attendees subject to security screening',
                'Smoking only in designated areas',
            ],
            'tips' => [
                'Arrive early for best parking',
                'Bring valid photo ID',
                'Download the event app for updates',
                'Check weather forecast and dress accordingly',
            ],
        ];
    }

    /**
     * Get random location details
     */
    private function getRandomLocationDetails($city, $address): array
    {
        return [
            'full_address' => ($address ?? '123 Main St') . ', ' . $city . ', USA',
            'directions' => 'Located in the heart of ' . $city . '. Easily accessible by public transportation.',
            'public_transport' => [
                'subway' => 'Multiple lines available',
                'bus' => 'Several bus routes nearby',
                'train' => 'Grand Central Terminal - 15 min walk',
            ],
            'parking_info' => [
                'available' => true,
                'price' => '$' . rand(15, 40) . '/day',
                'lots' => ['Main Parking Garage', 'Street Parking', 'Nearby Lots Available'],
            ],
            'map_url' => 'https://www.google.com/maps/place/' . urlencode($city),
            'venue_website' => 'https://venue-' . strtolower(str_replace(' ', '-', $city)) . '.com',
        ];
    }

    /**
     * Get random booking data
     */
    private function getRandomBooking(): array
    {
        return [
            'required' => true,
            'ticket_url' => 'https://tickets.example.com/event/' . uniqid(),
            'capacity' => rand(500, 20000),
            'waiting_list_available' => (bool) rand(0, 1),
            'ticket_types' => [
                'General Admission' => rand(50, 150),
                'VIP' => rand(150, 500),
                'Meet & Greet' => rand(300, 1000),
            ],
        ];
    }

    /**
     * Get random social links
     */
    private function getRandomSocialLinks(): array
    {
        return [
            'facebook' => 'https://facebook.com/event/' . rand(100000, 999999),
            'twitter' => 'https://twitter.com/event' . uniqid(),
            'instagram' => 'https://instagram.com/event' . uniqid(),
            'youtube' => 'https://youtube.com/channel/' . substr(md5(uniqid()), 0, 24),
        ];
    }

    /**
     * Get random tags
     */
    private function getRandomTags(): array
    {
        $allTags = [
            'music', 'festival', 'concert', 'live', 'outdoor', 'indoor',
            'summer', 'winter', 'electronic', 'rock', 'pop', 'jazz',
            'dance', 'party', 'nightlife', 'family', 'all-ages', '21+',
            'vip', 'weekend', 'holiday', 'special-event', 'charity'
        ];

        return array_rand(array_flip($allTags), rand(3, 8));
    }
}
