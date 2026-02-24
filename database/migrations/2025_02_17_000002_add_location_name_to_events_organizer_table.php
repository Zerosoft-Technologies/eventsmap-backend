<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add location_name column to events_organizer table.
     */
    public function up(): void
    {
        Schema::table('events_organizer', function (Blueprint $table) {
            $table->string('location_name')->nullable()->after('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events_organizer', function (Blueprint $table) {
            $table->dropColumn('location_name');
        });
    }
};
