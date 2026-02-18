<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Comprehensive migration to ensure all required fields exist in events_organizer table.
     */
    public function up(): void
    {
        Schema::table('events_organizer', function (Blueprint $table) {
            // Boolean fields
            if (!Schema::hasColumn('events_organizer', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_published');
            }
            
            if (!Schema::hasColumn('events_organizer', 'is_cancelled')) {
                $table->boolean('is_cancelled')->default(false)->after('is_featured');
            }
            
            if (!Schema::hasColumn('events_organizer', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('is_cancelled');
            }
            
            // Foreign key fields
            if (!Schema::hasColumn('events_organizer', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('short_description');
                $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('events_organizer', 'subcategory_id')) {
                $table->foreignId('subcategory_id')->nullable()->after('category_id');
                $table->foreign('subcategory_id')->references('id')->on('subcategories')->onDelete('set null');
            }
            
            // JSON fields
            if (!Schema::hasColumn('events_organizer', 'social_links')) {
                $table->json('social_links')->nullable()->after('booking');
            }
            
            // Ensure other required JSON fields exist
            $jsonFields = ['contact_info', 'about', 'location_details', 'booking', 'highlights', 'requirements', 'additional_info', 'custom_fields', 'tags', 'meta_keywords'];
            foreach ($jsonFields as $field) {
                if (!Schema::hasColumn('events_organizer', $field)) {
                    $table->json($field)->nullable();
                }
            }
            
            // Timestamp fields
            $timestampFields = ['published_at', 'featured_at', 'cancelled_at', 'archived_at'];
            foreach ($timestampFields as $field) {
                if (!Schema::hasColumn('events_organizer', $field)) {
                    $table->timestamp($field)->nullable();
                }
            }
            
            // Add indexes for performance
            $indexes = [
                'is_featured' => 'events_organizer_is_featured_index',
                'is_cancelled' => 'events_organizer_is_cancelled_index',
                'is_archived' => 'events_organizer_is_archived_index',
                'category_id' => 'events_organizer_category_id_index',
                'subcategory_id' => 'events_organizer_subcategory_id_index',
                'organizer_id' => 'events_organizer_organizer_id_index',
                'published_at' => 'events_organizer_published_at_index',
                'featured_at' => 'events_organizer_featured_at_index',
                'cancelled_at' => 'events_organizer_cancelled_at_index',
                'archived_at' => 'events_organizer_archived_at_index'
            ];
            
            foreach ($indexes as $column => $indexName) {
                if (Schema::hasColumn('events_organizer', $column) && !Schema::hasIndex('events_organizer', $indexName)) {
                    $table->index($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events_organizer', function (Blueprint $table) {
            // Drop foreign keys first
            if (Schema::hasColumn('events_organizer', 'subcategory_id')) {
                $table->dropForeign(['subcategory_id']);
                $table->dropColumn('subcategory_id');
            }
            
            if (Schema::hasColumn('events_organizer', 'category_id')) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            }
            
            // Drop other columns
            $columns = [
                'is_featured',
                'is_cancelled',
                'is_archived',
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
                'published_at',
                'featured_at',
                'cancelled_at',
                'archived_at'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('events_organizer', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
