<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add missing category_id and subcategory_id columns to events_organizer table.
     */
    public function up(): void
    {
        Schema::table('events_organizer', function (Blueprint $table) {
            if (!Schema::hasColumn('events_organizer', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('short_description');
                $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('events_organizer', 'subcategory_id')) {
                $table->foreignId('subcategory_id')->nullable()->after('category_id');
                $table->foreign('subcategory_id')->references('id')->on('subcategories')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events_organizer', function (Blueprint $table) {
            if (Schema::hasColumn('events_organizer', 'subcategory_id')) {
                $table->dropForeign(['subcategory_id']);
                $table->dropColumn('subcategory_id');
            }
            
            if (Schema::hasColumn('events_organizer', 'category_id')) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            }
        });
    }
};
