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
     * Make events_organizer table match events table exactly.
     */
    public function up(): void
    {
        Schema::table('events_organizer', function (Blueprint $table) {
            // Core fields - ensure category is NOT NULL like in events table
            if (Schema::hasColumn('events_organizer', 'category')) {
                // Make category NOT NULL to match events table
                $table->string('category')->nullable(false)->change();
            }
            
            // Ensure all core fields exist with same constraints
            if (!Schema::hasColumn('events_organizer', 'title')) {
                $table->string('title');
            }
            if (!Schema::hasColumn('events_organizer', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('events_organizer', 'price')) {
                $table->decimal('price', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('events_organizer', 'dresscode')) {
                $table->string('dresscode')->nullable();
            }
            if (!Schema::hasColumn('events_organizer', 'min_age')) {
                $table->integer('min_age')->nullable();
            }
            if (!Schema::hasColumn('events_organizer', 'start_datetime')) {
                $table->timestampTz('start_datetime');
            }
            if (!Schema::hasColumn('events_organizer', 'end_datetime')) {
                $table->timestampTz('end_datetime');
            }
            if (!Schema::hasColumn('events_organizer', 'city')) {
                $table->string('city');
            }
            if (!Schema::hasColumn('events_organizer', 'address')) {
                $table->string('address')->nullable();
            }
            if (!Schema::hasColumn('events_organizer', 'is_published')) {
                $table->boolean('is_published')->default(true);
            }
            
            // Extended fields from 2025_01_07 migration
            if (!Schema::hasColumn('events_organizer', 'slug')) {
                $table->string('slug')->unique()->nullable()->after('title');
            }
            if (!Schema::hasColumn('events_organizer', 'min_price')) {
                $table->decimal('min_price', 10, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('events_organizer', 'max_price')) {
                $table->decimal('max_price', 10, 2)->nullable()->after('min_price');
            }
            if (!Schema::hasColumn('events_organizer', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('max_price');
            }
            if (!Schema::hasColumn('events_organizer', 'timezone')) {
                $table->string('timezone', 50)->default('UTC')->after('end_datetime');
            }
            if (!Schema::hasColumn('events_organizer', 'venue_name')) {
                $table->string('venue_name')->nullable()->after('address');
            }
            if (!Schema::hasColumn('events_organizer', 'country')) {
                $table->string('country', 100)->nullable()->after('city');
            }
            if (!Schema::hasColumn('events_organizer', 'max_age')) {
                $table->integer('max_age')->nullable()->after('min_age');
            }
            if (!Schema::hasColumn('events_organizer', 'organizer_name')) {
                $table->string('organizer_name')->nullable()->after('max_age');
            }
            if (!Schema::hasColumn('events_organizer', 'organizer_id')) {
                $table->foreignId('organizer_id')->nullable()->after('organizer_name');
                $table->foreign('organizer_id')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('events_organizer', 'contact_info')) {
                $table->json('contact_info')->nullable()->after('organizer_id');
            }
            if (!Schema::hasColumn('events_organizer', 'cover_image')) {
                $table->string('cover_image')->nullable()->after('contact_info');
            }
            if (!Schema::hasColumn('events_organizer', 'video_url')) {
                $table->string('video_url')->nullable()->after('cover_image');
            }
            if (!Schema::hasColumn('events_organizer', 'about')) {
                $table->json('about')->nullable()->after('video_url');
            }
            if (!Schema::hasColumn('events_organizer', 'location_details')) {
                $table->json('location_details')->nullable()->after('about');
            }
            if (!Schema::hasColumn('events_organizer', 'booking')) {
                $table->json('booking')->nullable()->after('location_details');
            }
            if (!Schema::hasColumn('events_organizer', 'social_links')) {
                $table->json('social_links')->nullable()->after('booking');
            }
            if (!Schema::hasColumn('events_organizer', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_published');
            }
            if (!Schema::hasColumn('events_organizer', 'is_cancelled')) {
                $table->boolean('is_cancelled')->default(false)->after('is_featured');
            }
            if (!Schema::hasColumn('events_organizer', 'meta_title')) {
                $table->string('meta_title')->nullable()->after('is_cancelled');
            }
            if (!Schema::hasColumn('events_organizer', 'meta_description')) {
                $table->text('meta_description')->nullable()->after('meta_title');
            }
            if (!Schema::hasColumn('events_organizer', 'tags')) {
                $table->json('tags')->nullable()->after('meta_description');
            }
            if (!Schema::hasColumn('events_organizer', 'view_count')) {
                $table->unsignedBigInteger('view_count')->default(0)->after('tags');
            }
            
            // Additional fields from other migrations
            if (!Schema::hasColumn('events_organizer', 'capacity')) {
                $table->integer('capacity')->nullable()->after('is_free');
            }
            if (!Schema::hasColumn('events_organizer', 'registration_url')) {
                $table->string('registration_url', 500)->nullable()->after('capacity');
            }
            if (!Schema::hasColumn('events_organizer', 'registration_deadline')) {
                $table->timestamp('registration_deadline')->nullable()->after('registration_url');
            }
            if (!Schema::hasColumn('events_organizer', 'meta_keywords')) {
                $table->json('meta_keywords')->nullable()->after('meta_description');
            }
            if (!Schema::hasColumn('events_organizer', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('meta_keywords');
            }
            
            // Additional fields that might be missing
            if (!Schema::hasColumn('events_organizer', 'status')) {
                $table->string('status')->default('draft')->after('slug');
            }
            if (!Schema::hasColumn('events_organizer', 'short_description')) {
                $table->text('short_description')->nullable()->after('description');
            }
            if (!Schema::hasColumn('events_organizer', 'highlights')) {
                $table->json('highlights')->nullable()->after('tags');
            }
            if (!Schema::hasColumn('events_organizer', 'requirements')) {
                $table->json('requirements')->nullable()->after('highlights');
            }
            if (!Schema::hasColumn('events_organizer', 'additional_info')) {
                $table->json('additional_info')->nullable()->after('requirements');
            }
            if (!Schema::hasColumn('events_organizer', 'custom_fields')) {
                $table->json('custom_fields')->nullable()->after('additional_info');
            }
            if (!Schema::hasColumn('events_organizer', 'state')) {
                $table->string('state', 100)->nullable()->after('country');
            }
            if (!Schema::hasColumn('events_organizer', 'postal_code')) {
                $table->string('postal_code', 20)->nullable()->after('state');
            }
            if (!Schema::hasColumn('events_organizer', 'is_all_day')) {
                $table->boolean('is_all_day')->default(false)->after('timezone');
            }
            if (!Schema::hasColumn('events_organizer', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false)->after('is_all_day');
            }
            if (!Schema::hasColumn('events_organizer', 'is_ticketed')) {
                $table->boolean('is_ticketed')->default(false)->after('is_recurring');
            }
            if (!Schema::hasColumn('events_organizer', 'is_free')) {
                $table->boolean('is_free')->default(false)->after('is_ticketed');
            }
            if (!Schema::hasColumn('events_organizer', 'age_restriction')) {
                $table->text('age_restriction')->nullable()->after('dresscode');
            }
            if (!Schema::hasColumn('events_organizer', 'accessibility_info')) {
                $table->text('accessibility_info')->nullable()->after('age_restriction');
            }
            if (!Schema::hasColumn('events_organizer', 'contact_email')) {
                $table->string('contact_email', 255)->nullable()->after('organizer_id');
            }
            if (!Schema::hasColumn('events_organizer', 'contact_phone')) {
                $table->string('contact_phone', 20)->nullable()->after('contact_email');
            }
            if (!Schema::hasColumn('events_organizer', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('view_count');
            }
            if (!Schema::hasColumn('events_organizer', 'featured_at')) {
                $table->timestamp('featured_at')->nullable()->after('published_at');
            }
            if (!Schema::hasColumn('events_organizer', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('featured_at');
            }
            if (!Schema::hasColumn('events_organizer', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('events_organizer', 'morning')) {
                $table->boolean('morning')->default(false)->after('view_count');
            }
            if (!Schema::hasColumn('events_organizer', 'afternoon')) {
                $table->boolean('afternoon')->default(false)->after('morning');
            }
            if (!Schema::hasColumn('events_organizer', 'evening')) {
                $table->boolean('evening')->default(false)->after('afternoon');
            }
            if (!Schema::hasColumn('events_organizer', 'night')) {
                $table->boolean('night')->default(false)->after('evening');
            }
            if (!Schema::hasColumn('events_organizer', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('is_cancelled');
            }
        });
        
        // Add PostGIS location column if it doesn't exist (PostgreSQL only)
        if (DB::getDriverName() === 'pgsql') {
            $columnExists = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'events_organizer' AND column_name = 'location'");
            if (empty($columnExists)) {
                DB::statement('ALTER TABLE events_organizer ADD COLUMN location GEOGRAPHY(Point, 4326)');
                DB::statement('CREATE INDEX events_organizer_location_gist ON events_organizer USING GIST (location)');
            }
        }
        
        // Add all necessary indexes
        Schema::table('events_organizer', function (Blueprint $table) {
            $indexes = [
                'slug',
                'is_featured',
                'is_cancelled',
                'organizer_id',
                'status',
                'capacity',
                'registration_deadline',
                'published_at',
                'featured_at',
                'cancelled_at',
                'archived_at',
                ['is_all_day', 'is_recurring'],
                ['is_ticketed', 'is_free'],
                ['organizer_id', 'status'],
                ['organizer_id', 'is_published'],
                ['organizer_id', 'created_at'],
                ['organizer_id', 'start_datetime']
            ];
            
            foreach ($indexes as $index) {
                $indexName = is_array($index) ? implode('_', $index) : $index;
                if (!Schema::hasIndex('events_organizer', "events_organizer_{$indexName}_index")) {
                    $table->index($index);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is meant to sync the tables, so reversing would be complex
        // In practice, you would drop the table instead
        Schema::dropIfExists('events_organizer');
    }
};
