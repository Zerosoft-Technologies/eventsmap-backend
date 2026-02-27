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
            if (!Schema::hasColumn('users', 'full_name')) {
                $table->string('full_name')->nullable()->after('billing_type');
            }
            if (!Schema::hasColumn('users', 'company_name')) {
                $table->string('company_name')->nullable()->after('full_name');
            }
            if (!Schema::hasColumn('users', 'vat_validated')) {
                $table->boolean('vat_validated')->default(false)->after('vat_number');
            }
            if (!Schema::hasColumn('users', 'address')) {
                $table->string('address')->nullable()->after('country');
            }
            if (!Schema::hasColumn('users', 'postal_code')) {
                $table->string('postal_code')->nullable()->after('address');
            }
            if (!Schema::hasColumn('users', 'city')) {
                $table->string('city')->nullable()->after('postal_code');
            }
            if (!Schema::hasColumn('users', 'stripe_customer_id')) {
                $table->string('stripe_customer_id')->nullable()->after('city');
            }
            if (!Schema::hasColumn('users', 'stripe_session_id')) {
                $table->string('stripe_session_id')->nullable()->after('stripe_subscription_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'full_name',
                'company_name',
                'vat_validated',
                'address',
                'postal_code',
                'city',
                'stripe_customer_id',
                'stripe_session_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
