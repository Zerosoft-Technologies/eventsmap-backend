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
            $table->enum('profile_type', ['event', 'talent', 'organizer', 'venue'])->nullable()->after('role');
            $table->enum('account_type', ['free', 'premium'])->default('free')->after('profile_type');
            $table->enum('status', ['active', 'pending_payment', 'suspended'])->default('active')->after('account_type');
            $table->enum('billing_type', ['private', 'business'])->nullable()->after('status');
            $table->string('country')->nullable()->after('billing_type');
            $table->string('vat_number')->nullable()->after('country');
            $table->string('stripe_subscription_id')->nullable()->after('vat_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_type',
                'account_type',
                'status',
                'billing_type',
                'country',
                'vat_number',
                'stripe_subscription_id',
            ]);
        });
    }
};
