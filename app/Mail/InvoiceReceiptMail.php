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

    public string $userName;

    public string $companyName;

    public string $supportEmail;

    public ?string $downloadUrl;

    public function __construct(
        public Invoice $invoice,
    ) {
        $this->userName = VerifyEmailMail::displayNameForEmail($invoice->billing_name ?: 'there');
        $this->companyName = (string) config('invoice.company.name', config('app.name'));
        $this->supportEmail = (string) config('invoice.company.support_email', config('mail.from.address'));
        $frontend = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $this->downloadUrl = $frontend !== '' ? $frontend.'/account/invoices' : null;
    }

    public function envelope(): Envelope
    {
        $from = config('mail.from');

        return new Envelope(
            from: new Address($from['address'], $from['name']),
            to: [
                new Address($this->invoice->billing_email, $this->userName),
            ],
            subject: 'Your invoice '.$this->invoice->invoice_number.' — '.$this->companyName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invoice-receipt',
            with: [
                'invoice' => $this->invoice,
                'userName' => $this->userName,
                'companyName' => $this->companyName,
                'supportEmail' => $this->supportEmail,
                'downloadUrl' => $this->downloadUrl,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->invoice->invoice_pdf_path) {
            return [];
        }

        $disk = config('invoice.storage_disk', 'public');
        if (! Storage::disk($disk)->exists($this->invoice->invoice_pdf_path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk($disk, $this->invoice->invoice_pdf_path)
                ->as($this->invoice->invoice_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
