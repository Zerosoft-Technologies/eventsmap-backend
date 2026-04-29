<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events_v2')) {
            return;
        }

        Schema::table('events_v2', function (Blueprint $table) {
            if (! Schema::hasColumn('events_v2', 'venue_name')) {
                $table->string('venue_name', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events_v2')) {
            return;
        }

        Schema::table('events_v2', function (Blueprint $table) {
            if (Schema::hasColumn('events_v2', 'venue_name')) {
                $table->dropColumn('venue_name');
            }
        });
    }
};
