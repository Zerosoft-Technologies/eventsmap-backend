<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates analytics foundation tables for views and likes.
     */
    public function up(): void
    {
        // Event views tracking
        Schema::create('event_v2_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_v2_id')->constrained('events_v2')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('referrer')->nullable();
            $table->timestamp('viewed_at')->useCurrent();

            $table->index(['event_v2_id', 'viewed_at']);
            $table->index(['user_id', 'event_v2_id']);
        });

        // Event likes tracking
        Schema::create('event_v2_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_v2_id')->constrained('events_v2')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_v2_id', 'user_id']);
            $table->index('user_id');
        });

        // Add view_count and like_count cache columns to events_v2
        Schema::table('events_v2', function (Blueprint $table) {
            $table->unsignedBigInteger('view_count')->default(0)->after('is_free_package');
            $table->unsignedBigInteger('like_count')->default(0)->after('view_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events_v2', function (Blueprint $table) {
            $table->dropColumn(['view_count', 'like_count']);
        });

        Schema::dropIfExists('event_v2_likes');
        Schema::dropIfExists('event_v2_views');
    }
};
