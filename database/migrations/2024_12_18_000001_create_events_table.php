<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the events table with PostGIS GEOGRAPHY(Point, 4326) for location.
     * Uses GEOGRAPHY type for accurate distance calculations on Earth's surface.
     * SRID 4326 = WGS84 coordinate system (standard GPS coordinates).
     */
    public function up(): void
    {
        // Ensure PostGIS extension is enabled
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

        Schema::create('events', function (Blueprint $table) {
            // Core fields
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('dresscode')->nullable();
            $table->integer('min_age')->nullable();

            // Event timing (with timezone support)
            $table->timestampTz('start_datetime');
            $table->timestampTz('end_datetime');

            // Location (city and address stored as strings)
            $table->string('city');
            $table->string('address')->nullable();

            // Meta
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        // Add PostGIS GEOGRAPHY column for location
        // GEOGRAPHY type automatically handles distance in meters on Earth's surface
        DB::statement('ALTER TABLE events ADD COLUMN location GEOGRAPHY(Point, 4326)');

        // Create spatial index for efficient geo queries (ST_DWithin, etc.)
        DB::statement('CREATE INDEX events_location_gist ON events USING GIST (location)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
