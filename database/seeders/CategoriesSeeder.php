<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoriesSeeder extends Seeder
{
    /**
     * STRICT category order & IDs (stable across runs):
     * 1 Music, 2 Dance, 3 Theatre, 4 Community, 5 Nightlife,
     * 6 Film, 7 Talent, 8 Venue, 9 Organiser
     *
     * Notes:
     * - Uses explicit IDs to guarantee order everywhere (DB/API/UI).
     * - Uses upsert for idempotency and performance.
     */
    public function run(): void
    {
        $now = now();

        $rows = [
            ['id' => 1, 'name' => 'Music', 'slug' => 'music', 'display_order' => 1],
            ['id' => 2, 'name' => 'Dance', 'slug' => 'dance', 'display_order' => 2],
            ['id' => 3, 'name' => 'Theatre', 'slug' => 'theatre', 'display_order' => 3],
            ['id' => 4, 'name' => 'Community', 'slug' => 'community', 'display_order' => 4],
            ['id' => 5, 'name' => 'Nightlife', 'slug' => 'nightlife', 'display_order' => 5],
            ['id' => 6, 'name' => 'Film', 'slug' => 'film', 'display_order' => 6],
            ['id' => 7, 'name' => 'Talent', 'slug' => 'talent', 'display_order' => 7],
            ['id' => 8, 'name' => 'Venue', 'slug' => 'venue', 'display_order' => 8],
            ['id' => 9, 'name' => 'Organiser', 'slug' => 'organiser', 'display_order' => 9],
        ];

        // Ensure deterministic slug formatting even if someone edits name casing later.
        $rows = array_map(function (array $r) use ($now): array {
            $r['slug'] = $r['slug'] ?? Str::slug($r['name']);
            $r['description'] = $r['description'] ?? null;
            $r['icon'] = $r['icon'] ?? null;
            $r['color'] = $r['color'] ?? null;
            $r['is_active'] = $r['is_active'] ?? true;
            $r['is_featured'] = $r['is_featured'] ?? false;
            $r['created_at'] = $now;
            $r['updated_at'] = $now;
            return $r;
        }, $rows);

        DB::transaction(function () use ($rows): void {
            DB::table('categories')->upsert(
                $rows,
                ['id'],
                ['name', 'slug', 'description', 'icon', 'color', 'display_order', 'is_active', 'is_featured', 'updated_at']
            );

            // For PostgreSQL, keep the sequence in sync after explicit IDs.
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT setval(pg_get_serial_sequence('categories','id'), (SELECT MAX(id) FROM categories))");
            }
        });
    }
}

