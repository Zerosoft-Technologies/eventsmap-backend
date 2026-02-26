<?php

namespace Database\Factories;

use App\Models\EventV2;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wishlist>
 */
class WishlistFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Wishlist::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_v2_id' => EventV2::factory(),
            'created_at' => fake()->dateTimeBetween('-3 months', 'now'),
        ];
    }

    /**
     * Create a wishlist entry without creating new models.
     * Useful when you have existing users and events.
     */
    public function existing(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::inRandomOrder()->first()->id,
            'event_v2_id' => EventV2::inRandomOrder()->first()->id,
        ]);
    }
}
