<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Create event_organizer_images table for organizer event images.
     */
    public function up(): void
    {
        Schema::create('event_organizer_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_organizer_id')->constrained('events_organizer')->onDelete('cascade');
            $table->string('url');
            $table->string('alt_text')->nullable();
            $table->string('caption')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Indexes for performance
            $table->index('event_organizer_id');
            $table->index(['event_organizer_id', 'is_primary']);
            $table->index(['event_organizer_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_organizer_images');
    }
};
