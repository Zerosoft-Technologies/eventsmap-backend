<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add all missing JSON fields to events_organizer table to match the events table.
     */
    public function up(): void
    {
        Schema::table('events_organizer', function (Blueprint $table) {
            // Add social_links if missing
            if (!Schema::hasColumn('events_organizer', 'social_links')) {
                $table->json('social_links')->nullable()->after('booking');
            }
            
            // Ensure all other JSON fields exist
            if (!Schema::hasColumn('events_organizer', 'contact_info')) {
                $table->json('contact_info')->nullable()->after('organizer_id');
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
            
            if (!Schema::hasColumn('events_organizer', 'tags')) {
                $table->json('tags')->nullable()->after('meta_description');
            }
            
            if (!Schema::hasColumn('events_organizer', 'meta_keywords')) {
                $table->json('meta_keywords')->nullable()->after('meta_description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events_organizer', function (Blueprint $table) {
            $columns = [
                'social_links',
                'contact_info',
                'about',
                'location_details',
                'booking',
                'highlights',
                'requirements',
                'additional_info',
                'custom_fields',
                'tags',
                'meta_keywords',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('events_organizer', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
