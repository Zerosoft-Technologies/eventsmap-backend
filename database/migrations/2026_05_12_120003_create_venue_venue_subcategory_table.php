<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_venue_subcategory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venue_v2')->cascadeOnDelete();
            $table->foreignId('subcategory_id')->constrained('venue_subcategories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['venue_id', 'subcategory_id']);
            $table->index('venue_id');
            $table->index('subcategory_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_venue_subcategory');
    }
};
