<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class InvoiceReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        $from = config('mail.from');

        return new Envelope(
            from: new Address($from['address'], $from['name']),
            subject: 'Your invoice '.$this->invoice->invoice_number.' — '.config('invoice.company.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-receipt',
            with: [
                'invoice' => $this->invoice,
                'companyName' => config('invoice.company.name'),
                'supportEmail' => config('invoice.company.support_email'),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $disk = config('invoice.storage_disk', 'public');
        $path = Storage::disk($disk)->path($this->invoice->invoice_pdf_path);

        return [
            Attachment::fromPath($path)
                ->as($this->invoice->invoice_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
