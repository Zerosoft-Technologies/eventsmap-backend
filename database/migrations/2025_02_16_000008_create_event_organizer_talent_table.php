<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Create pivot table for organizer events and talents.
     */
    public function up(): void
    {
        Schema::create('event_organizer_talent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_organizer_id')->constrained('events_organizer')->onDelete('cascade');
            $table->foreignId('talent_id')->constrained('talents')->onDelete('cascade');
            $table->string('role')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Unique constraint to prevent duplicate entries
            $table->unique(['event_organizer_id', 'talent_id']);
            
            // Indexes for performance
            $table->index('event_organizer_id');
            $table->index('talent_id');
            $table->index(['event_organizer_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_organizer_talent');
    }
};
