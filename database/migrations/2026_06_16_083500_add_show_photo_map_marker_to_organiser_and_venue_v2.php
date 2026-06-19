<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Earlier migration targeted wrong table names (organisers_v2 / venues_v2).
 * Ensures show_photo_map_marker exists on the actual V2 profile tables.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $profileTables = ['organiser_v2', 'venue_v2'];

    public function up(): void
    {
        foreach ($this->profileTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'show_photo_map_marker')) {
                    $table->boolean('show_photo_map_marker')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->profileTables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'show_photo_map_marker')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('show_photo_map_marker');
            });
        }
    }
};
