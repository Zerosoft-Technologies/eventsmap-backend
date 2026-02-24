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
        Schema::table('users', function (Blueprint $table) {
            $table->index('email', 'users_email_index');
            $table->index('account_type', 'users_account_type_index');
            $table->index('status', 'users_status_index');
            $table->index('profile_type', 'users_profile_type_index');
            $table->index('country', 'users_country_index');
            $table->index(['account_type', 'status'], 'users_account_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_email_index');
            $table->dropIndex('users_account_type_index');
            $table->dropIndex('users_status_index');
            $table->dropIndex('users_profile_type_index');
            $table->dropIndex('users_country_index');
            $table->dropIndex('users_account_status_index');
        });
    }
};
