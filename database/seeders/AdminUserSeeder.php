<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates default admin users for the system.
     */
    public function run(): void
    {
        // Create Super Admin
        User::firstOrCreate(
            ['email' => 'superadmin@eventsmap.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_SUPER_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
                'profile_image_path' => 'https://api.dicebear.com/7.x/avataaars/svg?seed='.rawurlencode('superadmin@eventsmap.com'),
            ]
        );

        // Create Admin
        User::firstOrCreate(
            ['email' => 'admin@eventsmap.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
                'profile_image_path' => 'https://api.dicebear.com/7.x/avataaars/svg?seed='.rawurlencode('admin@eventsmap.com'),
            ]
        );

        $this->command->info('Admin users seeded successfully!');
        $this->command->info('Super Admin: superadmin@eventsmap.com / password');
        $this->command->info('Admin: admin@eventsmap.com / password');
    }
}
