<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_invitation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_invitation_id')->constrained('event_invitations')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events_v2')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('action', [
                'invitation_created',
                'invitation_email_sent',
                'invitation_accepted',
                'invitation_rejected',
            ]);
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('metadata')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'action']);
            $table->index('event_invitation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_invitation_logs');
    }
};
