<?php

namespace App\Jobs;

use App\Mail\InvoiceReceiptMail;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendInvoiceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $invoiceId,
    ) {}

    public function handle(InvoiceService $invoiceService): void
    {
        $invoice = Invoice::query()->with('user')->find($this->invoiceId);
        if (! $invoice) {
            return;
        }

        if ($invoice->emailed_at !== null) {
            return;
        }

        $email = $invoice->billing_email ?: $invoice->user?->email;
        if (! is_string($email) || $email === '') {
            Log::warning('Invoice email skipped: no recipient', ['invoice_id' => $invoice->id]);

            return;
        }

        $disk = config('invoice.storage_disk', 'public');

        if (! $invoice->invoice_pdf_path || ! Storage::disk($disk)->exists($invoice->invoice_pdf_path)) {
            try {
                $path = $invoiceService->generatePdf($invoice);
                $invoice->update(['invoice_pdf_path' => $path]);
                $invoice->refresh();
            } catch (\Throwable $e) {
                Log::error('Invoice PDF regeneration failed before email', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);

                return;
            }
        }

        if (! $invoice->billing_email && $email) {
            $invoice->update(['billing_email' => $email]);
            $invoice->refresh();
        }

        try {
            Mail::to($email)->send(new InvoiceReceiptMail($invoice));
            $invoice->update(['emailed_at' => now()]);

            Log::info('Invoice email sent', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'email' => $email,
            ]);
        } catch (\Throwable $e) {
            Log::error('Invoice email send failed', [
                'invoice_id' => $invoice->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
