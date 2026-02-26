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
        static $userIndex = 0;
        $userIndex++;
        
        $firstNames = ['John', 'Jane', 'Mike', 'Sarah', 'David', 'Emma', 'Chris', 'Lisa', 'Tom', 'Anna'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
        
        return [
            'name' => $firstNames[($userIndex - 1) % count($firstNames)] . ' ' . $lastNames[($userIndex - 1) % count($lastNames)],
            'email' => 'user' . $userIndex . '@example.com',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => User::ROLE_USER,
            'is_active' => true,
            'profile_type' => [User::PROFILE_EVENT, User::PROFILE_TALENT, User::PROFILE_ORGANIZER, User::PROFILE_VENUE][($userIndex - 1) % 4],
            'account_type' => [User::ACCOUNT_FREE, User::ACCOUNT_PREMIUM][($userIndex - 1) % 2],
            'status' => User::STATUS_ACTIVE,
            'billing_type' => ['private', 'business'][($userIndex - 1) % 2],
            'country' => 'NL',
            'vat_number' => ($userIndex % 3 === 0) ? 'NL' . str_pad((string)$userIndex, 8, '0', STR_PAD_LEFT) . 'B' . ($userIndex % 100) : null,
            'stripe_subscription_id' => ($userIndex % 5 === 0) ? 'sub_' . Str::random(20) : null,
            'created_at' => now()->subDays($userIndex),
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
