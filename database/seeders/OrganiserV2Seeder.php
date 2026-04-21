<?php

namespace Database\Seeders;

use App\Models\OrganiserSubcategory;
use App\Models\OrganiserV2;
use Database\Seeders\Concerns\GuardsProductionSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganiserV2Seeder extends Seeder
{
    use GuardsProductionSeeding;

    public function run(): void
    {
        if ($this->shouldAbortInProduction()) {
            return;
        }

        try {
            DB::transaction(function (): void {
                $clusters = [
                    ['city' => 'Amsterdam', 'count' => 8],
                    ['city' => 'London', 'count' => 8],
                    ['city' => 'Paris', 'count' => 6],
                    ['city' => 'Berlin', 'count' => 5],
                    ['city' => 'Barcelona', 'count' => 5],
                    ['city' => 'New York City', 'count' => 6],
                    ['city' => 'Mumbai', 'count' => 5],
                    ['city' => 'Dubai', 'count' => 4],
                    ['city' => 'Singapore', 'count' => 3],
                ];

                foreach ($clusters as $cluster) {
                    OrganiserV2::factory()
                        ->count($cluster['count'])
                        ->forCity($cluster['city'])
                        ->create()
                        ->each(function (OrganiserV2 $organiser): void {
                            $this->syncOrganiserSubcategories($organiser);
                        });
                }
            });
        } catch (\Throwable $e) {
            $this->command?->error('OrganiserV2Seeder failed: '.$e->getMessage());
            throw $e;
        }

        $this->command?->info('Seeded 50 Organisers');
    }

    private function syncOrganiserSubcategories(OrganiserV2 $organiser): void
    {
        $query = OrganiserSubcategory::query();
        if ($organiser->organiser_category_id !== null) {
            $query->where('organiser_category_id', $organiser->organiser_category_id);
        }

        $ids = $query->inRandomOrder()->limit(fake()->numberBetween(1, 3))->pluck('id')->all();
        if ($ids !== []) {
            $organiser->organiserSubcategories()->sync($ids);
        }
    }
}
