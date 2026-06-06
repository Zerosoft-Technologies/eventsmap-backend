<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_invitations', function (Blueprint $table) {
            $table->dropForeign(['receiver_id']);
        });

        Schema::table('event_invitations', function (Blueprint $table) {
            $table->unsignedBigInteger('receiver_id')->nullable()->change();
            $table->string('invitee_email', 255)->nullable()->after('receiver_id');
            $table->string('invitee_name', 255)->nullable()->after('invitee_email');
        });

        Schema::table('event_invitations', function (Blueprint $table) {
            $table->foreign('receiver_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['event_id', 'invitee_email', 'receiver_type'], 'event_invitations_guest_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('event_invitations', function (Blueprint $table) {
            $table->dropIndex('event_invitations_guest_lookup');
            $table->dropForeign(['receiver_id']);
            $table->dropColumn(['invitee_email', 'invitee_name']);
        });

        Schema::table('event_invitations', function (Blueprint $table) {
            $table->unsignedBigInteger('receiver_id')->nullable(false)->change();
            $table->foreign('receiver_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
