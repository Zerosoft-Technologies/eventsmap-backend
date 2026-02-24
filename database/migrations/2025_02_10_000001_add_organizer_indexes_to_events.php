<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add indexes for organizer queries optimization.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Composite index for organizer queries with common filters
            $table->index(['organizer_id', 'status']);
            $table->index(['organizer_id', 'is_published']);
            $table->index(['organizer_id', 'created_at']);
            $table->index(['organizer_id', 'start_datetime']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['organizer_id', 'status']);
            $table->dropIndex(['organizer_id', 'is_published']);
            $table->dropIndex(['organizer_id', 'created_at']);
            $table->dropIndex(['organizer_id', 'start_datetime']);
        });
    }
};
