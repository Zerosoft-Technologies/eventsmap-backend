<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => $this->faker->randomElement([User::ROLE_USER]),
            'is_active' => true,
            'profile_type' => $this->faker->randomElement([User::PROFILE_EVENT, User::PROFILE_TALENT, User::PROFILE_ORGANIZER, User::PROFILE_VENUE]),
            'account_type' => $this->faker->randomElement([User::ACCOUNT_FREE, User::ACCOUNT_PREMIUM]),
            'status' => User::STATUS_ACTIVE,
            'billing_type' => $this->faker->randomElement(['private', 'business']),
            'country' => 'NL',
            'vat_number' => $this->faker->optional(0.3)->numerify('NL#########B##'),
            'stripe_subscription_id' => $this->faker->optional(0.2)->uuid(),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create an admin user.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ADMIN,
            'email' => 'admin@example.com',
        ]);
    }

    /**
     * Create a super admin user.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_SUPER_ADMIN,
            'email' => 'superadmin@example.com',
        ]);
    }
}
