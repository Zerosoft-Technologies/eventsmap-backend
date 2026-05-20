<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('subscription_invoice_id')->nullable()->constrained('subscription_invoices')->nullOnDelete();

            $table->string('invoice_number')->unique();
            $table->string('order_id')->index();

            $table->string('stripe_payment_intent')->nullable()->unique();
            $table->string('stripe_session_id')->nullable()->unique();
            $table->string('stripe_invoice_id')->nullable()->unique();
            $table->string('stripe_subscription_id')->nullable()->index();

            $table->unsignedInteger('amount')->comment('Subtotal in smallest currency unit');
            $table->unsignedInteger('tax_amount')->default(0);
            $table->unsignedInteger('total_amount');
            $table->char('currency', 3)->default('eur');

            $table->string('payment_status', 32)->default('paid')->index();
            $table->string('status', 32)->default('issued')->index();

            $table->string('invoice_pdf_path')->nullable();

            $table->string('billing_name');
            $table->string('billing_email');
            $table->text('billing_address')->nullable();

            $table->string('product_description');
            $table->string('plan_interval', 32)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);

            $table->string('payment_method')->nullable();
            $table->string('payment_method_brand', 64)->nullable();
            $table->string('payment_method_last4', 16)->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('emailed_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
