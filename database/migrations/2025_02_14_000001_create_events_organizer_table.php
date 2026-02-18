<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Create events_organizer table with same structure as events
     */
    public function up(): void
    {
        Schema::create('events_organizer', function (Blueprint $table) {
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

            // Additional fields from extend_events_table migration
            $table->string('slug')->unique()->nullable()->after('title');
            $table->decimal('min_price', 10, 2)->nullable()->after('price');
            $table->decimal('max_price', 10, 2)->nullable()->after('min_price');
            $table->string('currency', 3)->default('USD')->after('max_price');
            $table->string('timezone', 50)->default('UTC')->after('end_datetime');
            $table->string('venue_name')->nullable()->after('address');
            $table->string('country', 100)->nullable()->after('city');
            $table->integer('max_age')->nullable()->after('min_age');
            $table->string('organizer_name')->nullable()->after('max_age');
            $table->foreignId('organizer_id')->nullable()->after('organizer_name');
            $table->json('contact_info')->nullable()->after('organizer_id');
            $table->string('cover_image')->nullable()->after('contact_info');
            $table->string('video_url')->nullable()->after('cover_image');
            $table->json('about')->nullable()->after('video_url');
            $table->json('location_details')->nullable()->after('about');
            $table->json('booking')->nullable()->after('location_details');
            
            // Additional fields from missing_event_fields migration
            $table->string('status')->default('draft')->after('slug');
            $table->text('short_description')->nullable()->after('description');
            $table->json('highlights')->nullable()->after('tags');
            $table->json('requirements')->nullable()->after('highlights');
            $table->json('additional_info')->nullable()->after('requirements');
            $table->json('custom_fields')->nullable()->after('additional_info');
            $table->string('state', 100)->nullable()->after('country');
            $table->string('postal_code', 20)->nullable()->after('state');
            $table->boolean('is_all_day')->default(false)->after('timezone');
            $table->boolean('is_recurring')->default(false)->after('is_all_day');
            $table->boolean('is_ticketed')->default(false)->after('is_recurring');
            $table->boolean('is_free')->default(false)->after('is_ticketed');
            $table->text('age_restriction')->nullable()->after('dresscode');
            $table->text('accessibility_info')->nullable()->after('age_restriction');
            $table->timestamp('published_at')->nullable()->after('view_count');
            $table->timestamp('featured_at')->nullable()->after('published_at');
            $table->timestamp('cancelled_at')->nullable()->after('featured_at');
            $table->timestamp('archived_at')->nullable()->after('cancelled_at');

            // Contact fields
            $table->string('contact_email', 255)->nullable()->after('organizer_id');
            $table->string('contact_phone', 20)->nullable()->after('contact_email');

            // Additional fields from 2025_01_28 migration
            $table->integer('capacity')->nullable()->after('is_free');
            $table->string('registration_url', 500)->nullable()->after('capacity');
            $table->timestamp('registration_deadline')->nullable()->after('registration_url');
            $table->json('meta_keywords')->nullable()->after('meta_description');
            $table->text('internal_notes')->nullable()->after('meta_keywords');

            // Other fields
            $table->integer('view_count')->default(0)->after('is_archived');
            $table->boolean('morning')->default(false)->after('view_count');
            $table->boolean('afternoon')->default(false)->after('morning');
            $table->boolean('evening')->default(false)->after('afternoon');
            $table->boolean('night')->default(false)->after('evening');
            $table->string('meta_title', 70)->nullable()->after('booking');
            $table->string('meta_description', 160)->nullable()->after('meta_title');
            $table->json('tags')->nullable()->after('meta_description');
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index(['is_all_day', 'is_recurring']);
            $table->index(['is_ticketed', 'is_free']);
            $table->index(['organizer_id', 'status']);
            $table->index(['organizer_id', 'is_published']);
            $table->index(['organizer_id', 'created_at']);
            $table->index(['organizer_id', 'start_datetime']);
            $table->index('organizer_id');
            $table->index('published_at');
            $table->index('featured_at');
            $table->index('cancelled_at');
            $table->index('archived_at');
        });

        // Add PostGIS GEOGRAPHY column for location (PostgreSQL only)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE events_organizer ADD COLUMN location GEOGRAPHY(Point, 4326)');
            // Create spatial index for efficient geo queries
            DB::statement('CREATE INDEX events_organizer_location_gist ON events_organizer USING GIST (location)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events_organizer');
    }
};
