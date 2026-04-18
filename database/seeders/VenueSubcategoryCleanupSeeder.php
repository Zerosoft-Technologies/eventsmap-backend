<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Venue (category_id = 8): upsert the 15 canonical rows, remap FKs off legacy duplicates, then delete those rows.
 * Run after seeders that may create old labels (e.g. V2DemoSeeder historically used "Government", "Hall", …).
 *
 *   php artisan db:seed --class=VenueSubcategoryCleanupSeeder
 */
class VenueSubcategoryCleanupSeeder extends Seeder
{
    private const CATEGORY_ID = 8;

    /**
     * Legacy duplicate names (category 8) -> canonical name to point FKs at before delete.
     */
    private const JUNK_NAME_TO_CANONICAL = [
        'Government' => 'Government Venue',
        'Hall' => 'Event Hall',
        'Night Club' => 'Nightclub',
        'Open Air' => 'Open-air Venue',
        'Punk' => 'Other',
    ];

    public function run(): void
    {
        $now = now();
        $canonicalRows = [];
        foreach ($this->canonicalRows() as $c) {
            $canonicalRows[] = [
                'category_id' => self::CATEGORY_ID,
                'name' => $c['name'],
                'slug' => $c['slug'],
                'description' => null,
                'display_order' => $c['display_order'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $canonical = $this->canonicalRows();

        DB::transaction(function () use ($canonicalRows, $canonical): void {
            DB::table('subcategories')->upsert(
                $canonicalRows,
                ['category_id', 'name'],
                ['slug', 'description', 'display_order', 'is_active', 'updated_at']
            );

            $canonicalIdsByName = DB::table('subcategories')
                ->where('category_id', self::CATEGORY_ID)
                ->whereIn('name', array_column($canonical, 'name'))
                ->pluck('id', 'name')
                ->all();

            if (count($canonicalIdsByName) !== count($canonical)) {
                throw new \RuntimeException('VenueSubcategoryCleanupSeeder: missing canonical Venue subcategories after upsert.');
            }

            $junkIds = [];
            foreach (self::JUNK_NAME_TO_CANONICAL as $junkName => $canonicalName) {
                $targetId = $canonicalIdsByName[$canonicalName] ?? null;
                if ($targetId === null) {
                    throw new \RuntimeException("VenueSubcategoryCleanupSeeder: no id for canonical \"{$canonicalName}\".");
                }

                $fromIds = DB::table('subcategories')
                    ->where('category_id', self::CATEGORY_ID)
                    ->where('name', $junkName)
                    ->pluck('id')
                    ->all();

                foreach ($fromIds as $fromId) {
                    $fromId = (int) $fromId;
                    if ($fromId === (int) $targetId) {
                        continue;
                    }
                    $junkIds[] = $fromId;
                    $this->remapSubcategoryId($fromId, (int) $targetId);
                }
            }

            $junkIds = array_values(array_unique($junkIds));
            if ($junkIds !== []) {
                DB::table('subcategories')->whereIn('id', $junkIds)->delete();
            }

            foreach ($canonical as $c) {
                $id = $canonicalIdsByName[$c['name']] ?? null;
                if ($id === null) {
                    continue;
                }
                DB::table('subcategories')->where('id', $id)->update([
                    'name' => $c['name'],
                    'slug' => $c['slug'],
                    'display_order' => $c['display_order'],
                    'updated_at' => now(),
                    'deleted_at' => null,
                    'is_active' => true,
                ]);
            }

            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT setval(pg_get_serial_sequence('subcategories','id'), (SELECT COALESCE(MAX(id), 1) FROM subcategories))");
            }
        });

        $this->command?->info('Venue subcategories: canonical list upserted; legacy duplicates removed (if any).');
    }

    private function remapSubcategoryId(int $fromId, int $toId): void
    {
        if ($fromId === $toId) {
            return;
        }

        DB::table('events')->where('subcategory_id', $fromId)->update(['subcategory_id' => $toId]);
        DB::table('events_organizer')->where('subcategory_id', $fromId)->update(['subcategory_id' => $toId]);
        DB::table('event_v2_subcategory')->where('subcategory_id', $fromId)->update(['subcategory_id' => $toId]);

        $this->dedupeEventV2SubcategoryPivot();

        $pairs = [$fromId => $toId];
        foreach (['events_v2', 'venues_v2', 'organisers_v2', 'talents_v2'] as $table) {
            if (! DB::getSchemaBuilder()->hasColumn($table, 'subcategory_ids')) {
                continue;
            }
            $this->remapJsonSubcategoryIdsColumn($table, 'subcategory_ids', $pairs);
        }
    }

    private function dedupeEventV2SubcategoryPivot(): void
    {
        DB::statement('
            DELETE FROM event_v2_subcategory a
            USING event_v2_subcategory b
            WHERE a.event_v2_id = b.event_v2_id
              AND a.subcategory_id = b.subcategory_id
              AND a.id > b.id
        ');
    }

    /**
     * @param  array<int, int>  $pairs
     */
    private function remapJsonSubcategoryIdsColumn(string $table, string $column, array $pairs): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $column, $pairs): void {
                foreach ($rows as $row) {
                    $raw = $row->{$column};
                    if ($raw === null || $raw === '') {
                        continue;
                    }
                    $ids = is_string($raw) ? json_decode($raw, true) : $raw;
                    if (! is_array($ids)) {
                        continue;
                    }
                    $changed = false;
                    $next = [];
                    foreach ($ids as $id) {
                        if (! is_numeric($id)) {
                            continue;
                        }
                        $int = (int) $id;
                        $mapped = $pairs[$int] ?? $int;
                        if ($mapped !== $int) {
                            $changed = true;
                        }
                        $next[] = $mapped;
                    }
                    $next = array_values(array_unique($next));
                    if ($changed || count($next) !== count($ids)) {
                        DB::table($table)->where('id', $row->id)->update([
                            $column => json_encode($next),
                        ]);
                    }
                }
            });
    }

    /**
     * @return list<array{name: string, slug: string, display_order: int}>
     */
    private function canonicalRows(): array
    {
        $names = [
            'Bar',
            'Casino',
            'Cinema',
            'Concert Hall',
            'Dance Venue',
            'Event Hall',
            'Government Venue',
            'Hotel',
            'Nightclub',
            'Open-air Venue',
            'Festival Grounds',
            'Restaurant',
            'Stadium',
            'Theatre',
            'Other',
        ];

        $rows = [];
        $order = 1;
        foreach ($names as $name) {
            $rows[] = [
                'name' => $name,
                'slug' => $this->explicitSlug($name),
                'display_order' => $order,
            ];
            $order++;
        }

        return $rows;
    }

    private function explicitSlug(string $name): string
    {
        return match ($name) {
            'Bar' => 'bar',
            'Casino' => 'casino',
            'Cinema' => 'cinema',
            'Concert Hall' => 'concert-hall',
            'Dance Venue' => 'dance-venue',
            'Event Hall' => 'event-hall',
            'Government Venue' => 'government-venue',
            'Hotel' => 'hotel',
            'Nightclub' => 'nightclub',
            'Open-air Venue' => 'open-air-venue',
            'Festival Grounds' => 'festival-grounds',
            'Restaurant' => 'restaurant',
            'Stadium' => 'stadium',
            'Theatre' => 'theatre',
            'Other' => 'other',
            default => Str::slug($name),
        };
    }
}
