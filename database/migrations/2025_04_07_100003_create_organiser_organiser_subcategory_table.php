<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organiser_organiser_subcategory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organiser_id')->constrained('organiser_v2')->cascadeOnDelete();
            $table->foreignId('subcategory_id')->constrained('organiser_subcategories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['organiser_id', 'subcategory_id']);
            $table->index('organiser_id');
            $table->index('subcategory_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organiser_organiser_subcategory');
    }
};
