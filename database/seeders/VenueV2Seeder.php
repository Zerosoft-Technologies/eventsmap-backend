<?php

namespace Database\Seeders;

use App\Models\VenueV2;
use Database\Seeders\Concerns\GuardsProductionSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VenueV2Seeder extends Seeder
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
                    ['city' => 'Amsterdam', 'count' => 6],
                    ['city' => 'London', 'count' => 6],
                    ['city' => 'Paris', 'count' => 5],
                    ['city' => 'Berlin', 'count' => 4],
                    ['city' => 'Barcelona', 'count' => 4],
                    ['city' => 'New York City', 'count' => 4],
                    ['city' => 'Mumbai', 'count' => 4],
                    ['city' => 'Dubai', 'count' => 3],
                    ['city' => 'Singapore', 'count' => 2],
                    ['city' => 'Sydney', 'count' => 2],
                ];

                foreach ($clusters as $cluster) {
                    VenueV2::factory()
                        ->count($cluster['count'])
                        ->forCity($cluster['city'])
                        ->seedRichContactPresentation()
                        ->create();
                }
            });
        } catch (\Throwable $e) {
            $this->command?->error('VenueV2Seeder failed: '.$e->getMessage());
            throw $e;
        }

        $this->command?->info('Seeded 40 Venues');
    }
}
