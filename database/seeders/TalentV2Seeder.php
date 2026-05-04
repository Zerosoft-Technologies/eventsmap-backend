<?php

namespace Database\Seeders;

use App\Models\TalentSubcategory;
use App\Models\TalentV2;
use Database\Seeders\Concerns\GuardsProductionSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TalentV2Seeder extends Seeder
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
                    ['city' => 'Amsterdam', 'count' => 7],
                    ['city' => 'London', 'count' => 7],
                    ['city' => 'Paris', 'count' => 5],
                    ['city' => 'Berlin', 'count' => 5],
                    ['city' => 'New York City', 'count' => 6],
                    ['city' => 'Los Angeles', 'count' => 5],
                    ['city' => 'Mumbai', 'count' => 5],
                    ['city' => 'Bangalore', 'count' => 4],
                    ['city' => 'Dubai', 'count' => 3],
                    ['city' => 'Singapore', 'count' => 3],
                ];

                foreach ($clusters as $cluster) {
                    TalentV2::factory()
                        ->count($cluster['count'])
                        ->forCity($cluster['city'])
                        ->seedRichContactPresentation()
                        ->create()
                        ->each(function (TalentV2 $talent): void {
                            $this->syncTalentSubcategories($talent);
                        });
                }
            });
        } catch (\Throwable $e) {
            $this->command?->error('TalentV2Seeder failed: '.$e->getMessage());
            throw $e;
        }

        $this->command?->info('Seeded 50 Talents');
    }

    private function syncTalentSubcategories(TalentV2 $talent): void
    {
        $query = TalentSubcategory::query();
        if ($talent->talent_category_id !== null) {
            $query->where('talent_category_id', $talent->talent_category_id);
        }

        $ids = $query->inRandomOrder()->limit(fake()->numberBetween(1, 3))->pluck('id')->all();
        if ($ids !== []) {
            $talent->talentSubcategories()->sync($ids);
        }
    }
}
