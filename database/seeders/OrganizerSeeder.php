<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class OrganizerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or get the organizer role
        $organizerRole = Role::firstOrCreate(['name' => 'organizer']);

        // Create sample organizer users
        $organizers = [
            [
                'name' => 'John Organizer',
                'email' => 'john@organizer.com',
                'password' => Hash::make('password'),
                'phone' => '+1-555-0101',
                'avatar' => null,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Jane Events',
                'email' => 'jane@organizer.com',
                'password' => Hash::make('password'),
                'phone' => '+1-555-0102',
                'avatar' => null,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Mike Productions',
                'email' => 'mike@organizer.com',
                'password' => Hash::make('password'),
                'phone' => '+1-555-0103',
                'avatar' => null,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($organizers as $organizerData) {
            $user = User::firstOrCreate(
                ['email' => $organizerData['email']],
                $organizerData
            );
            
            // Assign organizer role
            $user->assignRole($organizerRole);
            
            $this->command->info("Created organizer: {$user->email}");
        }
    }
}
