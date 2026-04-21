<?php

namespace Database\Seeders;

use App\Models\EventV2;
use App\Models\EventV2Like;
use App\Models\EventV2View;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Concerns\GuardsProductionSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EventV2Seeder extends Seeder
{
    use GuardsProductionSeeding;

    public function run(): void
    {
        if ($this->shouldAbortInProduction()) {
            return;
        }

        try {
            DB::transaction(function (): void {
                $cityClusters = [
                    ['city' => 'Amsterdam', 'count' => 15],
                    ['city' => 'London', 'count' => 15],
                    ['city' => 'Paris', 'count' => 12],
                    ['city' => 'Berlin', 'count' => 10],
                    ['city' => 'Barcelona', 'count' => 10],
                    ['city' => 'New York City', 'count' => 12],
                    ['city' => 'Los Angeles', 'count' => 8],
                    ['city' => 'Miami', 'count' => 8],
                    ['city' => 'Mumbai', 'count' => 10],
                    ['city' => 'Bangalore', 'count' => 8],
                    ['city' => 'Dubai', 'count' => 8],
                    ['city' => 'Singapore', 'count' => 7],
                    ['city' => 'Sydney', 'count' => 7],
                ];

                foreach ($cityClusters as $cluster) {
                    EventV2::factory()
                        ->count($cluster['count'])
                        ->forCity($cluster['city'])
                        ->create();
                }

                EventV2::factory()->count(6)->past()->create();
                EventV2::factory()->count(6)->upcoming()->create();
                EventV2::factory()->count(4)->live()->create();
                EventV2::factory()->count(4)->suspended()->create();

                EventV2::query()->orderBy('id')->get()->each(function (EventV2 $event): void {
                    $ids = $event->subcategory_ids;
                    if (is_array($ids) && $ids !== []) {
                        $event->subcategories()->sync($ids);
                    }
                });

                $this->seedSampleAnalytics();
            });
        } catch (\Throwable $e) {
            $this->command?->error('EventV2Seeder failed: '.$e->getMessage());
            throw $e;
        }

        $this->command?->info('Seeded 150 Events');

        $this->verifyUniqueCoordinates();
    }

    private function verifyUniqueCoordinates(): void
    {
        $tables = [
            'events_v2' => 'Events',
            'organiser_v2' => 'Organisers',
            'talents_v2' => 'Talents',
            'venue_v2' => 'Venues',
        ];

        foreach ($tables as $table => $label) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $duplicates = DB::table($table)
                ->select('latitude', 'longitude', DB::raw('COUNT(*) as cnt'))
                ->groupBy('latitude', 'longitude')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            if ($duplicates->isEmpty()) {
                $this->command?->info("✅ {$label}: All coordinates are unique.");
            } else {
                $this->command?->warn(
                    "⚠️  {$label}: Found {$duplicates->count()} duplicate coordinate pairs!"
                );
                foreach ($duplicates as $dup) {
                    $this->command?->warn(
                        "   → lat: {$dup->latitude}, lng: {$dup->longitude} appears {$dup->cnt} times"
                    );
                }
            }
        }
    }

    private function seedSampleAnalytics(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('event_v2_views') || ! DB::getSchemaBuilder()->hasTable('event_v2_likes')) {
            return;
        }

        $events = EventV2::query()->inRandomOrder()->get();
        if ($events->isEmpty()) {
            return;
        }

        $pick = min((int) max(1, ceil($events->count() * 0.3)), $events->count());
        $sample = $events->random($pick);

        $userIds = User::query()->pluck('id')->all();

        foreach ($sample as $event) {
            $viewRows = fake()->numberBetween(1, 200);
            for ($v = 0; $v < $viewRows; $v++) {
                EventV2View::query()->create([
                    'event_v2_id' => $event->id,
                    'user_id' => fake()->boolean(70) && $userIds !== [] ? fake()->randomElement($userIds) : null,
                    'ip_address' => fake()->ipv4(),
                    'user_agent' => fake()->userAgent(),
                    'referrer' => fake()->optional()->url(),
                    'viewed_at' => Carbon::parse(fake()->dateTimeBetween('-2 months', 'now')),
                ]);
            }

            $likeTarget = fake()->numberBetween(0, min(50, count($userIds)));
            if ($likeTarget > 0 && $userIds !== []) {
                shuffle($userIds);
                foreach (array_slice($userIds, 0, $likeTarget) as $uid) {
                    EventV2Like::query()->firstOrCreate([
                        'event_v2_id' => $event->id,
                        'user_id' => $uid,
                    ]);
                }
            }
        }
    }
}
