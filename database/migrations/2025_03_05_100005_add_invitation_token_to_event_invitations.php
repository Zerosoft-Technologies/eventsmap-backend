<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_invitations', function (Blueprint $table) {
            $table->string('invitation_token', 64)->nullable()->after('status');
            $table->timestamp('token_expires_at')->nullable()->after('invitation_token');
            $table->index('invitation_token');
        });
    }

    public function down(): void
    {
        Schema::table('event_invitations', function (Blueprint $table) {
            $table->dropIndex(['invitation_token']);
            $table->dropColumn(['invitation_token', 'token_expires_at']);
        });
    }
};
