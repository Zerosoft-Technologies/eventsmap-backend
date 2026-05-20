<?php

namespace App\Services;

use App\Jobs\SendInvoiceEmailJob;
use App\Mail\InvoiceReceiptMail;
use App\Mail\PremiumWelcomeMail;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class PremiumNotificationService
{
    /**
     * After successful premium payment: welcome email + invoice receipt (idempotent).
     */
    public function sendPostPaymentEmails(User $user, ?Invoice $invoice = null): void
    {
        $this->sendPremiumWelcomeIfNeeded($user);

        if ($invoice !== null) {
            $this->sendInvoiceReceipt($invoice->fresh());
        }
    }

    public function sendPremiumWelcomeIfNeeded(User $user): bool
    {
        if ($user->premium_welcome_sent_at !== null) {
            return false;
        }

        if (! $user->isPremiumAccount() || ! $user->isAccountActive()) {
            return false;
        }

        $email = $user->email;
        if (! is_string($email) || $email === '') {
            return false;
        }

        try {
            Mail::to($email)->send(new PremiumWelcomeMail($user->fresh()));
            $user->update(['premium_welcome_sent_at' => now()]);

            Log::info('Premium welcome email sent', [
                'user_id' => $user->id,
                'email' => $email,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Premium welcome email failed', [
                'user_id' => $user->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendInvoiceReceipt(Invoice $invoice): bool
    {
        if ($invoice->emailed_at !== null) {
            return false;
        }

        $invoice->loadMissing('user');

        $email = $invoice->billing_email ?: $invoice->user?->email;
        if (! is_string($email) || $email === '') {
            Log::warning('Invoice email skipped: no recipient', ['invoice_id' => $invoice->id]);

            return false;
        }

        $disk = config('invoice.storage_disk', 'public');

        if (! $invoice->invoice_pdf_path || ! Storage::disk($disk)->exists($invoice->invoice_pdf_path)) {
            try {
                $path = app(InvoiceService::class)->generatePdf($invoice);
                $invoice->update(['invoice_pdf_path' => $path]);
                $invoice->refresh();
            } catch (\Throwable $e) {
                Log::error('Invoice PDF missing before email', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        }

        if (! $invoice->billing_email) {
            $invoice->update(['billing_email' => $email]);
            $invoice->refresh();
        }

        try {
            Mail::to($email)->send(new InvoiceReceiptMail($invoice));
            $invoice->update(['emailed_at' => now()]);

            Log::info('Invoice receipt email sent', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'email' => $email,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Invoice receipt email failed', [
                'invoice_id' => $invoice->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function queueInvoiceReceipt(Invoice $invoice): void
    {
        if (config('invoice.email_async', false)) {
            SendInvoiceEmailJob::dispatch($invoice->id)
                ->onQueue(config('invoice.queue', 'default'))
                ->afterCommit();

            return;
        }

        $this->sendInvoiceReceipt($invoice);
    }
}
