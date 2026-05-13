<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_category_id')->constrained('venue_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->index('venue_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_subcategories');
    }
};
