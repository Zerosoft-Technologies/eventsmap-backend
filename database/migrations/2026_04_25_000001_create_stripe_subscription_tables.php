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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('account_id')->nullable()->index();
            $table->string('email')->index();

            $table->string('stripe_customer_id')->index();
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();

            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->string('stripe_price_id')->nullable()->index();
            $table->string('stripe_product_id')->nullable()->index();

            $table->string('subscription_status', 64)->index();
            $table->string('plan_name')->nullable();
            $table->string('billing_interval', 32)->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedInteger('amount')->nullable()->comment('Amount in smallest currency unit (e.g. cents)');
            $table->unsignedInteger('quantity')->default(1);

            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_start')->nullable();
            $table->timestamp('trial_end')->nullable();
            $table->timestamp('cancel_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('created_at_stripe')->nullable();
            $table->timestamp('updated_at_stripe')->nullable();

            $table->string('latest_invoice_id')->nullable();
            $table->string('latest_payment_intent_id')->nullable();
            $table->string('payment_status', 64)->nullable();
            $table->string('payment_method_brand', 64)->nullable();
            $table->string('payment_method_last4', 16)->nullable();

            $table->string('coupon_code')->nullable();
            $table->boolean('discount_applied')->default(false);
            $table->string('promo_code')->nullable();
            $table->decimal('tax_percent', 7, 4)->nullable();
            $table->string('country')->nullable();

            $table->string('checkout_session_id')->nullable()->unique();
            $table->string('billing_mode', 32)->default('subscription')->index();

            $table->json('raw_stripe_payload')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'subscription_status']);
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('stripe_invoice_id')->unique();
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->string('stripe_customer_id')->nullable()->index();

            $table->string('status', 64)->nullable()->index();
            $table->unsignedInteger('amount_due')->nullable();
            $table->unsignedInteger('amount_paid')->nullable();
            $table->unsignedInteger('amount_remaining')->nullable();
            $table->char('currency', 3)->nullable();

            $table->string('hosted_invoice_url')->nullable();
            $table->string('invoice_pdf')->nullable();

            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();

            $table->string('payment_intent_id')->nullable();
            $table->string('charge_id')->nullable();

            $table->json('raw_stripe_payload')->nullable();
            $table->timestamp('created_at_stripe')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('event_type', 128)->index();
            $table->string('api_version', 32)->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();

            $table->boolean('livemode')->default(false);
            $table->json('payload');

            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable()->index();

            $table->timestamps();
        });

        Schema::create('subscription_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();

            $table->string('stripe_payment_method_id')->unique();
            $table->string('brand', 64)->nullable();
            $table->string('last4', 16)->nullable();
            $table->unsignedSmallInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();

            $table->boolean('is_default')->default(false);

            $table->json('raw_stripe_payload')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_payment_methods');
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscriptions');
    }
};
