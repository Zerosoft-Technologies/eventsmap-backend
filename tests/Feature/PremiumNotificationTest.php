<?php

namespace Tests\Feature;

use App\Mail\InvoiceReceiptMail;
use App\Mail\PremiumWelcomeMail;
use App\Models\Invoice;
use App\Models\User;
use App\Services\PremiumNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PremiumNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['invoice.email_async' => false]);
    }

    public function test_sends_welcome_and_invoice_emails_after_payment(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_PREMIUM,
            'status' => User::STATUS_ACTIVE,
            'email' => 'premium@example.com',
        ]);

        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'invoice_number' => 'INV-TEST-000001',
            'order_id' => 'cs_test',
            'amount' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'currency' => 'eur',
            'payment_status' => Invoice::PAYMENT_PAID,
            'status' => Invoice::STATUS_PAID,
            'billing_name' => $user->name,
            'billing_email' => $user->email,
            'product_description' => 'Premium',
            'invoice_pdf_path' => 'invoices/INV-TEST-000001.pdf',
            'paid_at' => now(),
        ]);

        Storage::disk('public')->put($invoice->invoice_pdf_path, '%PDF-1.4 fake');

        app(PremiumNotificationService::class)->sendPostPaymentEmails($user, $invoice);

        Mail::assertSent(PremiumWelcomeMail::class, fn ($mail) => $mail->hasTo($user->email));
        Mail::assertSent(InvoiceReceiptMail::class, fn ($mail) => $mail->hasTo($user->email));

        $this->assertNotNull($user->fresh()->premium_welcome_sent_at);
        $this->assertNotNull($invoice->fresh()->emailed_at);
    }

    public function test_premium_welcome_is_not_sent_twice(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_PREMIUM,
            'status' => User::STATUS_ACTIVE,
            'premium_welcome_sent_at' => now(),
        ]);

        app(PremiumNotificationService::class)->sendPremiumWelcomeIfNeeded($user);

        Mail::assertNothingSent();
    }
}
