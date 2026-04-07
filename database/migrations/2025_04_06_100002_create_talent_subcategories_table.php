<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talent_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('talent_category_id')->constrained('talent_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->index('talent_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_subcategories');
    }
};
