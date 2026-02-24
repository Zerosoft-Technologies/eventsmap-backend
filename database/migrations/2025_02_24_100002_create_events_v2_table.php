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
        Schema::create('events_v2', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->date('event_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('address');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('dress_code')->default('casual');
            $table->string('age_limit')->default('all_ages');
            $table->string('entrance_status')->default('free');
            $table->string('image_path')->nullable();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->boolean('is_free_package')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('event_date');
            $table->index('status');
            $table->index('entrance_status');
            $table->index('category_id');
            $table->index('venue_id');
            $table->index('user_id');
            $table->index('is_free_package');
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events_v2');
    }
};
