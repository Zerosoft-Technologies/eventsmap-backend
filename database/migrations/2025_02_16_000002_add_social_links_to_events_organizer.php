<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add missing social_links column to events_organizer table.
     */
    public function up(): void
    {
        Schema::table('events_organizer', function (Blueprint $table) {
            if (!Schema::hasColumn('events_organizer', 'social_links')) {
                $table->json('social_links')->nullable()->after('booking');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events_organizer', function (Blueprint $table) {
            if (Schema::hasColumn('events_organizer', 'social_links')) {
                $table->dropColumn('social_links');
            }
        });
    }
};
