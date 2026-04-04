<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_v2', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('event_type')->default('free');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->json('subcategory_ids')->nullable();
            $table->string('address');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('image_path')->nullable();
            $table->json('additional_images')->nullable();
            $table->text('description')->nullable();
            $table->boolean('allow_dogs')->default(false);
            $table->boolean('wheelchair_accessible')->default(false);
            $table->boolean('parking')->default(false);
            $table->boolean('valet')->default(false);
            $table->boolean('play_area')->default(false);
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_website', 500)->nullable();
            $table->string('facebook_url', 500)->nullable();
            $table->string('instagram_url', 500)->nullable();
            $table->string('tiktok_url', 500)->nullable();
            $table->json('opening_hours')->nullable();
            $table->boolean('show_upcoming_events')->default(false);
            $table->boolean('show_past_events')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('user_id');
            $table->index('category_id');
            $table->index('event_type');
            $table->index('is_approved');

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_v2');
    }
};
