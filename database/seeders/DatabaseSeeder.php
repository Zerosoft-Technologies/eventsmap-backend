<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Factories\Support\CategoryImageProvider;
use Database\Factories\Support\CityDataProvider;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Seeder blocked in production.');

            return;
        }

        CityDataProvider::resetRegistry();
        CategoryImageProvider::resetImageRegistry();

        // When re-seeding without migrate:fresh, uncomment to avoid colliding with existing V2 rows:
        // CityDataProvider::loadExistingCoordinatesFromDB();

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            PrerequisiteSeeder::class,
            VenueV2Seeder::class,
            OrganiserV2Seeder::class,
            TalentV2Seeder::class,
            EventV2Seeder::class,
            AccountsV2Seeder::class,
            EventsSeeder::class,
            TalentSeeder::class,
            EventDetailsSeeder::class,
            VenueSubcategoryCleanupSeeder::class,
        ]);
    }
}
