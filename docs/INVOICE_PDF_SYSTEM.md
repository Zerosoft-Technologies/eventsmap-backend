# Invoice PDF system

Premium payments via Stripe automatically create a PDF invoice, store it, email it to the customer, and expose download APIs.

## Folder structure

```
app/
  Http/Controllers/InvoiceController.php
  Http/Resources/InvoiceResource.php
  Jobs/SendInvoiceEmailJob.php
  Mail/InvoiceReceiptMail.php
  Models/Invoice.php
  Services/InvoiceService.php
  Support/MoneyFormatter.php
  Support/TaxCalculator.php
config/invoice.php
database/migrations/2026_05_20_000001_create_invoices_table.php
resources/views/pdf/invoice.blade.php
resources/views/emails/invoice-receipt.blade.php
routes/api.php  (GET /api/invoices...)
public/images/invoice-logo.png  (optional company logo)
storage/app/public/invoices/     (generated PDFs)
```

## Flow

1. Customer completes Stripe Checkout (or subscription renews).
2. **Webhook** `checkout.session.completed` or `invoice.paid` → `SubscriptionPersistService` syncs data → `InvoiceService` issues invoice (idempotent).
3. **Payment verify** `POST /api/payment/verify` also calls `InvoiceService` (covers clients that confirm before webhook).
4. `InvoiceService`:
   - Generates unique number `INV-YYYYMMDD-000001`
   - Saves row in `invoices`
   - Renders `resources/views/pdf/invoice.blade.php` with DomPDF
   - Stores PDF under `storage/app/public/invoices/{number}.pdf`
   - Queues `SendInvoiceEmailJob` on `emails` queue

Duplicate protection: same `stripe_session_id`, `stripe_payment_intent`, or `stripe_invoice_id` will not create a second row.

## Setup (local)

```bash
composer require barryvdh/laravel-dompdf
php artisan migrate
php artisan storage:link
```

Logo for PDF: set `INVOICE_LOGO_PATH=images/marker.png` (under `public/`) or a full URL. The resolver embeds the image as base64 so DomPDF renders it reliably.

Emails use the same branded layout as verify/reset emails (`x-mail::layout`). By default they are sent **synchronously** immediately after the PDF is saved (`INVOICE_EMAIL_ASYNC=false`). Two emails are sent after a successful premium payment:

1. **Premium welcome** (`PremiumWelcomeMail`) — once per user (`premium_welcome_sent_at`)
2. **Invoice receipt** (`InvoiceReceiptMail`) — PDF attached, once per invoice (`emailed_at`)

Premium **signup** (`POST /api/auth/register` with `account_type=premium`) does not send welcome mail until payment completes (user is `pending_payment`). After Stripe Checkout + `POST /api/payment/verify` (or webhook), both emails are triggered.

If `INVOICE_EMAIL_ASYNC=true`, set `INVOICE_EMAIL_QUEUE=default` and run `php artisan queue:work`.

Configure `.env`:

```env
INVOICE_COMPANY_NAME="Events Map"
INVOICE_COMPANY_ADDRESS="Street, City, Country"
INVOICE_COMPANY_VAT_NUMBER="BE0123456789"
INVOICE_COMPANY_EMAIL=support@example.com
INVOICE_DEFAULT_TAX_RATE=21
QUEUE_CONNECTION=database   # or redis
```

Run queue worker (required for email):

```bash
php artisan queue:work --queue=emails,default
```

## API — Super Admin (auth: Sanctum + `super_admin`)

Base: `/api/admin/invoices` (requires admin login token; user role must be `super_admin`).

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/admin/invoices/stats` | Dashboard totals / revenue summary |
| GET | `/api/admin/invoices` | Paginated list of **all** invoices (filters below) |
| GET | `/api/admin/invoices/{id}` | Single invoice with customer + subscription |
| GET | `/api/admin/invoices/{id}/download` | PDF file download |

List query params: `page`, `per_page` (max 100), `search`, `user_id`, `payment_status`, `status`, `currency`, `paid_from`, `paid_to`, `created_from`, `created_to`, `sort` (`created_at`, `paid_at`, `total_amount`, `invoice_number`), `order` (`asc`, `desc`).

## API (auth: Sanctum) — customer

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/invoices` | Paginated list (`page`, `per_page`) |
| GET | `/api/invoices/{id}` | Invoice metadata + `pdf_url` |
| GET | `/api/invoices/{id}/download` | PDF file download |

Example list response:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "invoice_number": "INV-20260520-000001",
      "order_id": "cs_test_xxx",
      "total_amount": 12100,
      "currency": "EUR",
      "payment_status": "paid",
      "pdf_url": "http://localhost:8001/storage/invoices/INV-20260520-000001.pdf"
    }
  ],
  "pagination": { "current_page": 1, "per_page": 15, "total": 1, "total_pages": 1, "has_more": false }
}
```

## Production

1. Run migrations on deploy.
2. `php artisan storage:link` on each app server (or use S3: set `INVOICE_STORAGE_DISK=s3` and configure `filesystems.php`).
3. Run queue workers for `emails` queue.
4. Ensure Stripe webhooks include `checkout.session.completed` and `invoice.paid`.
5. Set real company/VAT values in environment.

## Testing

```bash
php artisan test --filter=InvoiceApiTest
php artisan test --filter=TaxCalculatorTest
```

Manual: complete a test Checkout payment → check `invoices` table and `storage/app/public/invoices/`.
