<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('action', ['warn', 'mute', 'unmute', 'ban', 'unban', 'delete_message']);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events_v2')->cascadeOnDelete();
            $table->string('message_id')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('performed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event_id', 'action']);
            $table->index(['event_id', 'action']);
            $table->index('performed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_moderation_logs');
    }
};
