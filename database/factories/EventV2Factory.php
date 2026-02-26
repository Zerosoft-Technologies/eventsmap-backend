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
    public function definition(): array
    {
        // Netherlands cities and their approximate coordinates
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

        $city = $this->faker->randomElement(array_keys($nlCities));
        $baseLat = $nlCities[$city]['lat'];
        $baseLng = $nlCities[$city]['lng'];

        // Add some random variation within the city (±0.05 degrees ≈ ±5km)
        $latitude = $baseLat + $this->faker->randomFloat(2, -0.05, 0.05);
        $longitude = $baseLng + $this->faker->randomFloat(2, -0.05, 0.05);

        // Event dates - mix of past, present, and future
        $eventDate = $this->faker->dateTimeBetween('-3 months', '+6 months');
        $startTime = $this->faker->dateTimeBetween('08:00', '23:00');
        
        // End time can be same day or next day (overnight events)
        $endTime = $this->faker->randomElement([
            $this->faker->dateTimeBetween($startTime, '23:59'),
            $this->faker->dateTimeBetween('00:00', '06:00'),
        ]);

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

        return [
            'user_id' => User::factory(),
            'category_id' => $category->id,
            'venue_id' => Venue::inRandomOrder()->first()?->id,
            'title' => $this->faker->sentence(3, false),
            'slug' => function (array $attributes) {
                return Str::slug($attributes['title']) . '-' . $this->faker->unique()->randomNumber(4);
            },
            'event_date' => $eventDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'address' => $this->faker->streetAddress(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'dress_code' => $this->faker->randomElement(['casual', 'smart casual', 'formal', 'black tie']),
            'age_limit' => $this->faker->randomElement(['all_ages', '18+', '21+']),
            'entrance_status' => $this->faker->randomElement(['free', 'paid', 'sold_out']),
            'image_path' => 'events/demo/' . $this->faker->numberBetween(1, 10) . '.jpg',
            'status' => $status,
            'is_free_package' => true,
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'updated_at' => now(),
        ];
    }

    /**
     * Create an upcoming event.
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_date' => $this->faker->dateTimeBetween('+1 day', '+6 months'),
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
            'event_date' => $this->faker->dateTimeBetween('-3 months', '-1 day'),
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
        $startTime = $this->faker->dateTimeBetween('20:00', '23:00');
        
        return $this->state(fn (array $attributes) => [
            'start_time' => $startTime,
            'end_time' => $this->faker->dateTimeBetween('00:00', '04:00'),
        ]);
    }
}
