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
use Faker\Factory as Faker;

class EventV2Seeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        DB::transaction(function () use ($faker) {

            $categories = Category::pluck('id')->toArray();
            if (empty($categories)) {
                $cat = Category::firstOrCreate(
                    ['slug' => 'music'],
                    ['name' => 'Music']
                );
                $categories = [$cat->id];
            }

            $users = User::pluck('id')->toArray();
            if (empty($users)) {
                return;
            }

            $venues = Venue::pluck('id')->toArray();
            $talentUsers = User::where('profile_type', 'talent')->pluck('id')->toArray();
            $organiserUsers = User::whereIn('profile_type', ['organiser', 'organizer'])->pluck('id')->toArray();

            // Create Premium Events
            for ($i = 1; $i <= 5; $i++) {
                $this->createPremiumEvent($faker, $users, $categories, $venues, $talentUsers, $organiserUsers);
            }

            // Create Free Events
            for ($i = 1; $i <= 3; $i++) {
                $this->createFreeEvent($faker, $users, $categories);
            }
        });
    }

    private function createPremiumEvent($faker, array $users, array $categories, array $venues, array $talentUsers, array $organiserUsers): void
    {
        $userId = $users[array_rand($users)];
        $categoryId = $categories[array_rand($categories)];
        $slug = Str::slug($faker->sentence(3)) . '-' . Str::random(8);

        $event = EventV2::create([
            'user_id' => $userId,
            'title' => $faker->sentence(4),
            'slug' => $slug,
            'event_type' => 'premium',
            'category_id' => $categoryId,
            'event_date' => $faker->dateTimeBetween('+1 week', '+6 months'),
            'start_time' => $faker->time('H:i:s'),
            'end_time' => $faker->time('H:i:s'),
            'address' => $faker->streetAddress() . ', ' . $faker->city(),
            'latitude' => $faker->latitude(),
            'longitude' => $faker->longitude(),
            'dress_code' => $faker->randomElement(['casual', 'smart_casual', 'formal']),
            'age_limit' => $faker->randomElement(['all_ages', '18+', '21+']),
            'entrance_status' => 'paid',
            'entrance_fee' => $faker->randomFloat(2, 10, 150),
            'contact_phone' => $faker->phoneNumber(),
            'contact_email' => $faker->safeEmail(),
            'contact_website' => $faker->url(),
            'description' => $faker->paragraphs(3, true),
            'venue_details' => $faker->paragraph(),
            'facebook_url' => 'https://facebook.com/' . Str::random(10),
            'instagram_url' => 'https://instagram.com/' . Str::random(10),
            'ticket_url' => $faker->url(),
            'additional_images' => [
                'events/demo/' . $faker->uuid() . '.jpg',
                'events/demo/' . $faker->uuid() . '.jpg',
            ],
            'venue_id' => !empty($venues) ? $venues[array_rand($venues)] : null,
            'status' => EventV2::STATUS_UPCOMING,
            'is_free_package' => false,
            'image_path' => 'events/demo/' . $faker->uuid() . '.jpg',
        ]);

        $subcategories = SubCategory::where('category_id', $categoryId)
            ->inRandomOrder()
            ->take(3)
            ->pluck('id');

        $event->subcategories()->attach($subcategories);

        if (!empty($talentUsers)) {
            $event->invitedTalents()->attach(
                array_slice($talentUsers, 0, min(2, count($talentUsers)))
            );
        }

        if (!empty($organiserUsers)) {
            $event->invitedOrganisers()->attach(
                array_slice($organiserUsers, 0, min(2, count($organiserUsers)))
            );
        }

        if (!empty($venues)) {
            $event->invitedVenues()->attach(
                array_slice($venues, 0, min(2, count($venues)))
            );
        }
    }

    private function createFreeEvent($faker, array $users, array $categories): void
    {
        $userId = $users[array_rand($users)];
        $categoryId = $categories[array_rand($categories)];
        $slug = Str::slug($faker->sentence(3)) . '-' . Str::random(8);

        $event = EventV2::create([
            'user_id' => $userId,
            'title' => $faker->sentence(4),
            'slug' => $slug,
            'event_type' => 'free',
            'category_id' => $categoryId,
            'event_date' => $faker->dateTimeBetween('+1 week', '+3 months'),
            'start_time' => $faker->time('H:i:s'),
            'end_time' => $faker->time('H:i:s'),
            'address' => $faker->streetAddress() . ', ' . $faker->city(),
            'latitude' => $faker->latitude(),
            'longitude' => $faker->longitude(),
            'dress_code' => 'casual',
            'age_limit' => 'all_ages',
            'entrance_status' => 'free',
            'status' => EventV2::STATUS_UPCOMING,
            'is_free_package' => true,
            'image_path' => 'events/demo/' . $faker->uuid() . '.jpg',
        ]);

        $subcategories = SubCategory::where('category_id', $categoryId)
            ->inRandomOrder()
            ->take(2)
            ->pluck('id');

        $event->subcategories()->attach($subcategories);
    }
}