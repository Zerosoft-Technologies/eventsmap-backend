<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['events_v2', 'talents_v2', 'venue_v2', 'organiser_v2'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'publish_status')) {
                    $table->string('publish_status', 32)->default('draft');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['events_v2', 'talents_v2', 'venue_v2', 'organiser_v2'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'publish_status')) {
                    $table->dropColumn('publish_status');
                }
            });
        }
    }
};
