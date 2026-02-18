<?php

namespace Database\Seeders;

use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EventOrganizerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get organizer users
        $organizers = User::role('organizer')->get();
        
        if ($organizers->isEmpty()) {
            $this->command->info('No organizer users found. Please run OrganizerSeeder first.');
            return;
        }

        // Disable foreign key checks temporarily
        Schema::disableForeignKeyConstraints();

        // Create sample organizer events
        foreach ($organizers as $organizer) {
            for ($i = 1; $i <= 5; $i++) {
                $title = "{$organizer->name}'s Event {$i}";
                $slug = Str::slug($title) . '-' . uniqid();
                
                $event = EventOrganizer::create([
                    'title' => $title,
                    'slug' => $slug,
                    'status' => ['draft', 'published', 'featured'][array_rand(['draft', 'published', 'featured'])],
                    'description' => "This is a detailed description for {$title}. It includes all the important information about the event.",
                    'short_description' => "Brief description for {$title}",
                    'category_id' => rand(1, 5),
                    'subcategory_id' => rand(1, 10),
                    'price' => rand(50, 500) + 0.99,
                    'min_price' => rand(25, 100) + 0.99,
                    'max_price' => rand(200, 1000) + 0.99,
                    'currency' => 'USD',
                    'dresscode' => ['Casual', 'Formal', 'Business'][array_rand(['Casual', 'Formal', 'Business'])],
                    'age_restriction' => ['All Ages', '18+', '21+'][array_rand(['All Ages', '18+', '21+'])],
                    'min_age' => rand(0, 21),
                    'max_age' => null,
                    'start_datetime' => now()->addDays(rand(1, 90))->setTime(rand(10, 20), 0),
                    'end_datetime' => now()->addDays(rand(1, 90))->setTime(rand(21, 23), 0),
                    'timezone' => 'America/New_York',
                    'is_all_day' => rand(0, 1) === 1,
                    'is_recurring' => false,
                    'venue_name' => 'Venue ' . rand(1, 10),
                    'address' => rand(100, 999) . ' Main St',
                    'city' => ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix'][array_rand(['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix'])],
                    'state' => ['NY', 'CA', 'IL', 'TX', 'AZ'][array_rand(['NY', 'CA', 'IL', 'TX', 'AZ'])],
                    'postal_code' => rand(10000, 99999),
                    'country' => 'USA',
                    'organizer_name' => $organizer->name,
                    'organizer_id' => $organizer->id,
                    'contact_email' => $organizer->email,
                    'contact_phone' => '+1-555-' . rand(100, 999) . '-' . rand(1000, 9999),
                    'contact_info' => [
                        'email' => $organizer->email,
                        'phone' => '+1-555-' . rand(100, 999) . '-' . rand(1000, 9999),
                        'website' => 'https://' . Str::slug($organizer->name) . '.com'
                    ],
                    'cover_image' => 'https://picsum.photos/seed/' . $slug . '/1200/600.jpg',
                    'video_url' => 'https://youtube.com/watch?v=' . substr(md5($slug), 0, 11),
                    'about' => [
                        'description' => "About {$title}",
                        'mission' => 'To provide the best experience',
                        'history' => 'This event started in 2020'
                    ],
                    'location_details' => [
                        'parking' => 'Available on site',
                        'public_transport' => 'Near subway station',
                        'directions' => 'Easy to find'
                    ],
                    'booking' => [
                        'website' => 'https://example.com/book',
                        'phone' => '+1-555-BOOK',
                        'email' => 'book@example.com'
                    ],
                    'social_links' => [
                        'facebook' => 'https://facebook.com/' . $slug,
                        'twitter' => 'https://twitter.com/' . $slug,
                        'instagram' => 'https://instagram.com/' . $slug
                    ],
                    'highlights' => [
                        'Amazing performances',
                        'Great food and drinks',
                        'Networking opportunities',
                        'Professional speakers'
                    ],
                    'requirements' => [
                        'Valid ID required',
                        'Dress code enforced',
                        'No outside food'
                    ],
                    'additional_info' => "Additional information for {$title}",
                    'accessibility_info' => 'Wheelchair accessible venue',
                    'is_ticketed' => rand(0, 1) === 1,
                    'is_free' => rand(0, 1) === 1,
                    'capacity' => rand(100, 5000),
                    'registration_url' => 'https://example.com/register/' . $slug,
                    'registration_deadline' => now()->addDays(rand(1, 30)),
                    'meta_keywords' => ['event', 'organizer', strtolower($organizer->name), 'entertainment'],
                    'internal_notes' => 'Internal notes for admin only',
                    'custom_fields' => [
                        'special_notes' => 'VIP handling required',
                        'equipment' => 'Projector and sound system needed'
                    ],
                    'meta_title' => $title . ' - Book Now',
                    'meta_description' => 'Join us for ' . $title . ' - an unforgettable experience',
                    'tags' => ['event', strtolower($organizer->name), 'entertainment', 'networking'],
                    'morning' => rand(0, 1) === 1,
                    'afternoon' => rand(0, 1) === 1,
                    'evening' => rand(0, 1) === 1,
                    'night' => rand(0, 1) === 1,
                    'view_count' => rand(100, 5000),
                ]);

                // Set status flags and timestamps
                $now = now();
                if ($event->status === 'published') {
                    $event->update([
                        'is_published' => true,
                        'published_at' => $now,
                    ]);
                } elseif ($event->status === 'featured') {
                    $event->update([
                        'is_published' => true,
                        'is_featured' => true,
                        'published_at' => $now,
                        'featured_at' => $now,
                    ]);
                }

                // Set random coordinates (US cities approximate)
                $coordinates = [
                    ['lat' => 40.7128, 'lng' => -74.0060], // New York
                    ['lat' => 34.0522, 'lng' => -118.2437], // Los Angeles
                    ['lat' => 41.8781, 'lng' => -87.6298], // Chicago
                    ['lat' => 29.7604, 'lng' => -95.3698], // Houston
                    ['lat' => 33.4484, 'lng' => -112.0740], // Phoenix
                ];
                
                $coord = $coordinates[array_rand($coordinates)];
                $event->setLocationFromCoordinates($coord['lat'], $coord['lng']);

                $this->command->info("Created organizer event: {$event->title}");
            }
        }

        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();
    }
}
