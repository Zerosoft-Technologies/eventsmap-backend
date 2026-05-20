<?php

namespace App\Jobs;

use App\Mail\InvoiceReceiptMail;
use App\Models\Invoice;
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

    public function handle(): void
    {
        $invoice = Invoice::query()->with('user')->find($this->invoiceId);
        if (! $invoice) {
            return;
        }

        if ($invoice->emailed_at !== null) {
            return;
        }

        $disk = config('invoice.storage_disk', 'public');
        if (! $invoice->invoice_pdf_path || ! Storage::disk($disk)->exists($invoice->invoice_pdf_path)) {
            Log::warning('Invoice PDF missing for email', ['invoice_id' => $invoice->id]);

            return;
        }

        Mail::to($invoice->billing_email)->send(new InvoiceReceiptMail($invoice));

        $invoice->update(['emailed_at' => now()]);
    }
}
