<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add missing boolean fields to events_organizer table.
     */
    public function up(): void
    {
        Schema::table('events_organizer', function (Blueprint $table) {
            // Add is_featured if missing
            if (!Schema::hasColumn('events_organizer', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_published');
            }
            
            // Add is_cancelled if missing
            if (!Schema::hasColumn('events_organizer', 'is_cancelled')) {
                $table->boolean('is_cancelled')->default(false)->after('is_featured');
            }
            
            // Add is_archived if missing
            if (!Schema::hasColumn('events_organizer', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('is_cancelled');
            }
            
            // Add indexes for performance
            if (!Schema::hasIndex('events_organizer', 'events_organizer_is_featured_index')) {
                $table->index('is_featured');
            }
            
            if (!Schema::hasIndex('events_organizer', 'events_organizer_is_cancelled_index')) {
                $table->index('is_cancelled');
            }
            
            if (!Schema::hasIndex('events_organizer', 'events_organizer_is_archived_index')) {
                $table->index('is_archived');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events_organizer', function (Blueprint $table) {
            $columns = ['is_featured', 'is_cancelled', 'is_archived'];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('events_organizer', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
