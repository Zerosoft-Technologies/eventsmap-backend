<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\EventV2;
use App\Models\SubCategory;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventV2Seeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            $categories = Category::pluck('id')->toArray();
            $users = User::pluck('id')->toArray();
            $venues = Venue::pluck('id')->toArray();

            if (empty($categories) || empty($users)) {
                return;
            }

            $talentUsers = User::where('profile_type', 'talent')->pluck('id')->toArray();
            $organiserUsers = User::whereIn('profile_type', ['organiser', 'organizer'])->pluck('id')->toArray();

            for ($i = 1; $i <= 5; $i++) {
                $this->createPremiumEvent($users, $categories, $venues, $talentUsers, $organiserUsers, $i);
            }

            for ($i = 1; $i <= 3; $i++) {
                $this->createFreeEvent($users, $categories, $i);
            }
        });
    }

    private function createPremiumEvent($users, $categories, $venues, $talentUsers, $organiserUsers, $index)
    {
        $userId = $users[array_rand($users)];
        $categoryId = $categories[array_rand($categories)];

        $event = EventV2::create([
            'user_id' => $userId,
            'title' => "Premium Event $index",
            'slug' => Str::slug("Premium Event $index") . '-' . Str::random(6),
            'event_type' => 'premium',
            'category_id' => $categoryId,
            'event_date' => now()->addDays(rand(5, 120)),
            'start_time' => '18:00:00',
            'end_time' => '22:00:00',
            'address' => "Demo Street $index, City",
            'latitude' => '9.9252',
            'longitude' => '78.1198',
            'dress_code' => 'casual',
            'age_limit' => '18+',
            'entrance_status' => 'paid',
            'entrance_fee' => rand(20, 150),
            'contact_phone' => '9999999999',
            'contact_email' => "event$index@test.com",
            'contact_website' => 'https://example.com',
            'description' => 'This is a sample premium event description.',
            'venue_details' => 'Main hall with parking.',
            'facebook_url' => 'https://facebook.com/demo',
            'instagram_url' => 'https://instagram.com/demo',
            'ticket_url' => 'https://tickets.example.com',
            'additional_images' => [
                'events/demo/sample1.jpg',
                'events/demo/sample2.jpg'
            ],
            'venue_id' => !empty($venues) ? $venues[array_rand($venues)] : null,
            'status' => EventV2::STATUS_UPCOMING,
            'is_free_package' => false,
            'image_path' => 'events/demo/main.jpg',
        ]);

        $subcategories = SubCategory::where('category_id', $categoryId)
            ->inRandomOrder()
            ->take(3)
            ->pluck('id');

        $event->subcategories()->attach($subcategories);

        if (!empty($talentUsers)) {
            $event->invitedTalents()->attach(array_slice($talentUsers, 0, 2));
        }

        if (!empty($organiserUsers)) {
            $event->invitedOrganisers()->attach(array_slice($organiserUsers, 0, 2));
        }

        if (!empty($venues)) {
            $event->invitedVenues()->attach(array_slice($venues, 0, 2));
        }
    }

    private function createFreeEvent($users, $categories, $index)
    {
        $userId = $users[array_rand($users)];
        $categoryId = $categories[array_rand($categories)];

        $event = EventV2::create([
            'user_id' => $userId,
            'title' => "Free Event $index",
            'slug' => Str::slug("Free Event $index") . '-' . Str::random(6),
            'event_type' => 'free',
            'category_id' => $categoryId,
            'event_date' => now()->addDays(rand(3, 60)),
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
            'address' => "Community Hall $index",
            'latitude' => '9.9252',
            'longitude' => '78.1198',
            'dress_code' => 'casual',
            'age_limit' => 'all_ages',
            'entrance_status' => 'free',
            'status' => EventV2::STATUS_UPCOMING,
            'is_free_package' => true,
            'image_path' => 'events/demo/free.jpg',
        ]);

        $subcategories = SubCategory::where('category_id', $categoryId)
            ->inRandomOrder()
            ->take(2)
            ->pluck('id');

        $event->subcategories()->attach($subcategories);
    }
}