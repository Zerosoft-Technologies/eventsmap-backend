<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['talents_v2', 'venue_v2', 'organiser_v2'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'status')) {
                    // No ->after(): PostgreSQL ignores/does not support column positioning like MySQL.
                    $table->string('status', 32)->default('draft');
                    $table->index('status');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['talents_v2', 'venue_v2', 'organiser_v2'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'status')) {
                    $table->dropColumn('status');
                }
            });
        }
    }
};
