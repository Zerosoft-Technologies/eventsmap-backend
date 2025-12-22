<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds session columns to the events table.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('morning')->default(false)->after('subcategory_id');
            $table->boolean('afternoon')->default(false)->after('morning');
            $table->boolean('evening')->default(false)->after('afternoon');
            $table->boolean('night')->default(false)->after('evening');
            
            // Add indexes for potential filtering by sessions
            $table->index('morning');
            $table->index('afternoon');
            $table->index('evening');
            $table->index('night');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['morning']);
            $table->dropIndex(['afternoon']);
            $table->dropIndex(['evening']);
            $table->dropIndex(['night']);
            $table->dropColumn(['morning', 'afternoon', 'evening', 'night']);
        });
    }
};
