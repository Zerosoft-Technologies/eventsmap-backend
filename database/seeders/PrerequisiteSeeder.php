<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Venue;
use Database\Factories\Support\CityDataProvider;
use Database\Seeders\Concerns\GuardsProductionSeeding;
use Database\Seeders\Concerns\WritesPlaceholderMedia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ensures baseline reference data exists before V2 profile factories run.
 *
 * Delegates to existing idempotent seeders where possible (categories, organiser/talent taxonomies).
 */
class PrerequisiteSeeder extends Seeder
{
    use GuardsProductionSeeding;
    use WritesPlaceholderMedia;

    public function run(): void
    {
        if ($this->shouldAbortInProduction()) {
            return;
        }

        try {
            DB::transaction(function (): void {
                $this->call([
                    AdminUserSeeder::class,
                    CategoriesSeeder::class,
                    SubcategoriesSeeder::class,
                    TalentCategorySeeder::class,
                    OrganiserCategorySeeder2::class,
                ]);

                $this->ensureMinimumUsers(10);
                $this->ensureMinimumLegacyVenues(10);

                $this->ensureV2PlaceholderImages();
                $this->ensureStorageLink();
            });
        } catch (\Throwable $e) {
            $this->command?->error('PrerequisiteSeeder failed: '.$e->getMessage());
            throw $e;
        }

        $this->command?->info('PrerequisiteSeeder: users, categories, taxonomies, venues, and placeholders are ready.');
    }

    private function ensureMinimumUsers(int $minimum): void
    {
        while (User::query()->count() < $minimum) {
            User::factory()->create([
                'email' => fake()->unique()->safeEmail(),
            ]);
        }
    }

    private function ensureMinimumLegacyVenues(int $minimum): void
    {
        if (Venue::query()->count() >= $minimum) {
            return;
        }

        $ownerId = User::query()->value('id');
        if ($ownerId === null) {
            return;
        }

        $needed = $minimum - Venue::query()->count();
        for ($i = 0; $i < $needed; $i++) {
            $name = 'Seeded Venue '.Str::upper(Str::random(6));
            $city = CityDataProvider::getUniqueCoordinates(CityDataProvider::getWeightedCity());
            Venue::query()->create([
                'user_id' => $ownerId,
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::lower(Str::replace('-', '', (string) Str::uuid())),
                'address' => CityDataProvider::randomFormattedAddress($city),
                'latitude' => $city['lat'],
                'longitude' => $city['lng'],
                'city' => $city['city'],
                'country' => $city['country'],
                'phone' => fake()->phoneNumber(),
                'email' => fake()->companyEmail(),
                'website' => fake()->url(),
                'description' => fake()->sentence(12),
                'image_path' => 'venues/images/placeholder.jpg',
                'capacity' => fake()->numberBetween(80, 5000),
                'is_active' => true,
            ]);
        }
    }
}
