<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events_v2')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->enum('receiver_type', ['talent', 'organiser', 'venue']);
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'receiver_id'], 'unique_event_receiver');
            $table->index(['receiver_id', 'status']);
            $table->index(['event_id', 'status']);
            $table->index('sender_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_invitations');
    }
};
