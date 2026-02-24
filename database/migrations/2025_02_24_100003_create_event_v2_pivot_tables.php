<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Event <-> Subcategory (max 5 per event for free package)
        Schema::create('event_v2_subcategory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_v2_id')->constrained('events_v2')->cascadeOnDelete();
            $table->foreignId('subcategory_id')->constrained('subcategories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_v2_id', 'subcategory_id']);
        });

        // Event <-> Organiser (many-to-many with users)
        Schema::create('event_v2_organiser', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_v2_id')->constrained('events_v2')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_v2_id', 'user_id']);
        });

        // Event <-> Talent (many-to-many)
        Schema::create('event_v2_talent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_v2_id')->constrained('events_v2')->cascadeOnDelete();
            $table->foreignId('talent_id')->constrained('talents')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['event_v2_id', 'talent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_v2_talent');
        Schema::dropIfExists('event_v2_organiser');
        Schema::dropIfExists('event_v2_subcategory');
    }
};
