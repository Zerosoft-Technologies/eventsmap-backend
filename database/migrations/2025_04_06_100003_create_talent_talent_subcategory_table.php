<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talent_talent_subcategory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('talent_id')->constrained('talents_v2')->cascadeOnDelete();
            $table->foreignId('subcategory_id')->constrained('talent_subcategories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['talent_id', 'subcategory_id']);
            $table->index('talent_id');
            $table->index('subcategory_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_talent_subcategory');
    }
};
