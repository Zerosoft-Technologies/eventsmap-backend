<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $profileTables = ['talents_v2', 'venues_v2', 'organisers_v2'];

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

        foreach (['events_v2', 'events'] as $eventsTable) {
            if (! Schema::hasTable($eventsTable)) {
                continue;
            }

            Schema::table($eventsTable, function (Blueprint $table) use ($eventsTable) {
                if (! Schema::hasColumn($eventsTable, 'show_photo_map_marker')) {
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

        foreach (['events_v2', 'events'] as $eventsTable) {
            if (! Schema::hasTable($eventsTable) || ! Schema::hasColumn($eventsTable, 'show_photo_map_marker')) {
                continue;
            }

            Schema::table($eventsTable, function (Blueprint $table) {
                $table->dropColumn('show_photo_map_marker');
            });
        }
    }
};
