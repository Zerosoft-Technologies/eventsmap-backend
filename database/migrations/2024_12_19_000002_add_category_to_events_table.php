<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds category_id and subcategory_id to events table,
     * and migrates existing string categories to proper relationships.
     */
    public function up(): void
    {
        // Add new columns first
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('description');
            $table->foreignId('subcategory_id')->nullable()->after('category_id');
            
            // Add foreign key constraints
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('restrict');
            $table->foreign('subcategory_id')->references('id')->on('subcategories')->onDelete('set null');
        });

        // Migrate existing string categories to IDs
        // This maps the old string values to the new category system
        $categoryMapping = [
            'music' => 'music',
            'dance' => 'dance',
            'theatre' => 'theatre',
            'nightlife' => 'nightlife',
            'film' => 'film',
        ];

        foreach ($categoryMapping as $oldCategory => $newSlug) {
            // Get the category ID
            $categoryId = \DB::table('categories')->where('slug', $newSlug)->value('id');
            
            if ($categoryId) {
                // Update events with the new category_id
                \DB::table('events')
                    ->where('category', $oldCategory)
                    ->update(['category_id' => $categoryId]);
            }
        }

        // Drop the old category string column
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the old category column
        Schema::table('events', function (Blueprint $table) {
            $table->string('category')->nullable()->after('description');
        });

        // Restore string categories from IDs (basic mapping)
        $reverseMapping = [
            'music' => 1,
            'dance' => 6,
            'theatre' => 5,
            'nightlife' => 3,
            'film' => 4,
        ];

        foreach ($reverseMapping as $categoryName => $categoryId) {
            \DB::table('events')
                ->where('category_id', $categoryId)
                ->update(['category' => $categoryName]);
        }

        // Drop foreign keys and columns
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['subcategory_id']);
            $table->dropForeign(['category_id']);
            $table->dropColumn(['subcategory_id', 'category_id']);
        });
    }
};
