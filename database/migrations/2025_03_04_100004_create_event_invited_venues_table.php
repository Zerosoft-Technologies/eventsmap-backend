<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_invited_venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events_v2')->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'venue_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_invited_venues');
    }
};
