<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organiser_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organiser_category_id')->constrained('organiser_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->index('organiser_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organiser_subcategories');
    }
};
