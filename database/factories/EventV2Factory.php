<?php

namespace Database\Factories;

use App\Models\EventV2;
use App\Models\User;
use App\Models\Category;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventV2>
 */
class EventV2Factory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = EventV2::class;

    /**
     * Define the model's default state.
     */
    public function definition()
    {
        static $eventIndex = 0;
        $eventIndex++;
        
        // Dutch cities with their coordinates
        $nlCities = [
            'Amsterdam' => ['lat' => 52.3676, 'lng' => 4.9041],
            'Rotterdam' => ['lat' => 51.9244, 'lng' => 4.4777],
            'The Hague' => ['lat' => 52.0799, 'lng' => 4.3113],
            'Utrecht' => ['lat' => 52.0907, 'lng' => 5.1214],
            'Eindhoven' => ['lat' => 51.4416, 'lng' => 5.4697],
            'Groningen' => ['lat' => 53.2194, 'lng' => 6.5665],
            'Maastricht' => ['lat' => 50.8514, 'lng' => 5.6910],
            'Leiden' => ['lat' => 52.1601, 'lng' => 4.4970],
            'Delft' => ['lat' => 52.0116, 'lng' => 4.3571],
            'Haarlem' => ['lat' => 52.3874, 'lng' => 4.6461],
        ];

        $cityKeys = array_keys($nlCities);
        $city = $cityKeys[($eventIndex - 1) % count($cityKeys)];
        $baseLat = $nlCities[$city]['lat'];
        $baseLng = $nlCities[$city]['lng'];

        // Add some variation within the city
        $latitude = $baseLat + ((($eventIndex * 7) % 100) - 50) / 1000;
        $longitude = $baseLng + ((($eventIndex * 11) % 100) - 50) / 1000;

        // Event dates
        $eventDate = now()->addDays(($eventIndex % 180) - 90);
        $startTime = now()->setTime(8 + (($eventIndex * 3) % 15), ($eventIndex * 7) % 60);
        $endTime = (clone $startTime)->addHours(3 + (($eventIndex * 2) % 5));

        // Determine status based on event date
        $now = now();
        if ($eventDate > $now) {
            $status = EventV2::STATUS_UPCOMING;
        } elseif ($eventDate->format('Y-m-d') === $now->format('Y-m-d')) {
            $status = EventV2::STATUS_LIVE;
        } else {
            $status = EventV2::STATUS_COMPLETED;
        }

        // Get random category from the main categories
        $category = Category::inRandomOrder()->first() 
            ?? Category::factory()->create(['slug' => 'default', 'name' => 'Default Category']);

        $eventTitles = [
            'Summer Music Festival', 'Tech Conference 2024', 'Art Exhibition Opening',
            'Food & Wine Tasting', 'Business Networking Event', 'Comedy Night Show',
            'Sports Championship', 'Charity Fundraiser', 'Product Launch Event',
            'Cultural Dance Performance', 'Science Workshop', 'Movie Premiere Night'
        ];

        $addresses = [
            'Main Street 123', 'Park Avenue 456', 'Central Square 78',
            'Harbor Front 234', 'Old Town 567', 'Business District 89',
            'University Campus 345', 'City Center 678', 'Riverside 901',
            'Market Square 234'
        ];

        return [
            'user_id' => User::factory(),
            'category_id' => $category->id,
            'venue_id' => Venue::inRandomOrder()->first()?->id,
            'title' => $eventTitles[($eventIndex - 1) % count($eventTitles)],
            'slug' => function (array $attributes) {
                return Str::slug($attributes['title']) . '-' . substr(md5($attributes['title']), 0, 4);
            },
            'event_date' => $eventDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'address' => $addresses[($eventIndex - 1) % count($addresses)] . ', ' . $city,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'dress_code' => ['casual', 'smart casual', 'formal', 'black tie'][($eventIndex - 1) % 4],
            'age_limit' => ['all_ages', '18+', '21+'][($eventIndex - 1) % 3],
            'entrance_status' => ['free', 'paid', 'sold_out'][($eventIndex - 1) % 3],
            'image_path' => 'events/demo/' . (($eventIndex - 1) % 10 + 1) . '.jpg',
            'status' => $status,
            'is_free_package' => true,
            'created_at' => now()->subDays($eventIndex),
            'updated_at' => now(),
        ];
    }

    /**
     * Create an upcoming event.
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_date' => now()->addDays(rand(1, 180)),
            'status' => EventV2::STATUS_UPCOMING,
        ]);
    }

    /**
     * Create a live event (today).
     */
    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_date' => now(),
            'status' => EventV2::STATUS_LIVE,
        ]);
    }

    /**
     * Create a past event.
     */
    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_date' => now()->subDays(rand(1, 90)),
            'status' => EventV2::STATUS_COMPLETED,
        ]);
    }

    /**
     * Create a paid event.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'entrance_status' => 'paid',
        ]);
    }

    /**
     * Create an overnight event.
     */
    public function overnight(): static
    {
        $startTime = now()->setTime(20 + rand(0, 3), rand(0, 59));
        
        return $this->state(fn (array $attributes) => [
            'start_time' => $startTime,
            'end_time' => (clone $startTime)->addHours(4 + rand(0, 4)),
        ]);
    }
}
