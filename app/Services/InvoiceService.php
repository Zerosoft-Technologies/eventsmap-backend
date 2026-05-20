<?php

namespace App\Services;

use App\Jobs\SendInvoiceEmailJob;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Support\MoneyFormatter;
use App\Support\TaxCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Invoice as StripeInvoice;

class InvoiceService
{
    /**
     * Idempotent: issue PDF invoice after Stripe Checkout (one-time or subscription start).
     */
    public function issueFromCheckoutSession(StripeCheckoutSession $session, User $user, ?Subscription $subscription = null): ?Invoice
    {
        if ($session->payment_status !== 'paid') {
            return null;
        }

        $existing = Invoice::query()
            ->where('stripe_session_id', $session->id)
            ->first();
        if ($existing) {
            $this->ensurePdfAndEmail($existing);

            return $existing;
        }

        $subscription = $subscription ?? Subscription::query()
            ->where('checkout_session_id', $session->id)
            ->orWhere('user_id', $user->id)
            ->latest('id')
            ->first();

        $piId = is_string($session->payment_intent)
            ? $session->payment_intent
            : ($session->payment_intent->id ?? null);

        if ($piId) {
            $byPi = Invoice::query()->where('stripe_payment_intent', $piId)->first();
            if ($byPi) {
                $this->ensurePdfAndEmail($byPi);

                return $byPi;
            }
        }

        $totalCents = (int) ($session->amount_total ?? 0);
        $taxCents = (int) ($session->total_details->amount_tax ?? 0);
        $subtotalCents = max(0, $totalCents - $taxCents);
        $amounts = TaxCalculator::fromStripeAmounts(
            $subtotalCents > 0 ? $subtotalCents : null,
            $taxCents > 0 ? $taxCents : null,
            $totalCents > 0 ? $totalCents : null,
            $subscription?->tax_percent ? (float) $subscription->tax_percent : null,
        );

        $planName = $subscription?->plan_name ?? 'Premium Account';
        $interval = $subscription?->billing_interval ?? ($session->mode === 'subscription' ? 'subscription' : 'one_time');

        return $this->createAndFinalize([
            'user_id' => $user->id,
            'subscription_id' => $subscription?->id,
            'order_id' => $session->id,
            'stripe_payment_intent' => $piId,
            'stripe_session_id' => $session->id,
            'stripe_invoice_id' => is_string($session->invoice) ? $session->invoice : ($session->invoice->id ?? null),
            'stripe_subscription_id' => is_string($session->subscription)
                ? $session->subscription
                : ($session->subscription->id ?? $subscription?->stripe_subscription_id),
            'amount' => $amounts['subtotal'],
            'tax_amount' => $amounts['tax_amount'],
            'total_amount' => $amounts['total'],
            'currency' => $session->currency ?? $subscription?->currency ?? 'eur',
            'payment_status' => Invoice::PAYMENT_PAID,
            'status' => Invoice::STATUS_PAID,
            'billing_name' => $subscription?->customer_name ?? $user->name,
            'billing_email' => $subscription?->customer_email ?? $user->email,
            'billing_address' => $this->formatBillingAddress($user),
            'product_description' => $this->productDescription($planName, $interval, $subscription),
            'plan_interval' => $interval,
            'quantity' => (int) ($subscription?->quantity ?? 1),
            'payment_method' => 'Stripe',
            'payment_method_brand' => $subscription?->payment_method_brand,
            'payment_method_last4' => $subscription?->payment_method_last4,
            'paid_at' => now(),
            'metadata' => ['source' => 'checkout.session.completed'],
        ]);
    }

    /**
     * Idempotent: issue PDF invoice when Stripe sends invoice.paid / payment_succeeded.
     */
    public function issueFromStripeInvoice(
        StripeInvoice $stripeInvoice,
        User $user,
        ?SubscriptionInvoice $subscriptionInvoice = null,
        ?Subscription $subscription = null,
    ): ?Invoice {
        if (! in_array($stripeInvoice->status, ['paid', 'open'], true) && (int) ($stripeInvoice->amount_paid ?? 0) <= 0) {
            return null;
        }

        $existing = Invoice::query()
            ->where('stripe_invoice_id', $stripeInvoice->id)
            ->first();
        if ($existing) {
            $this->ensurePdfAndEmail($existing);

            return $existing;
        }

        $pi = $stripeInvoice->payment_intent ?? null;
        $piIdEarly = is_string($pi) ? $pi : ($pi?->id ?? $subscriptionInvoice?->payment_intent_id);
        if ($piIdEarly) {
            $byPi = Invoice::query()->where('stripe_payment_intent', $piIdEarly)->first();
            if ($byPi) {
                if (! $byPi->stripe_invoice_id) {
                    $byPi->update(['stripe_invoice_id' => $stripeInvoice->id]);
                }
                $this->ensurePdfAndEmail($byPi);

                return $byPi;
            }
        }

        $subscription = $subscription ?? ($subscriptionInvoice?->subscription_id
            ? Subscription::query()->find($subscriptionInvoice->subscription_id)
            : null);

        $stripeSubId = is_string($stripeInvoice->subscription)
            ? $stripeInvoice->subscription
            : ($stripeInvoice->subscription->id ?? null);

        if (! $subscription && $stripeSubId) {
            $subscription = Subscription::query()->forStripeSubscription($stripeSubId)->first();
        }

        $totalCents = (int) ($stripeInvoice->amount_paid ?? $stripeInvoice->total ?? 0);
        $taxCents = (int) ($stripeInvoice->tax ?? 0);
        $subtotalCents = (int) ($stripeInvoice->subtotal ?? max(0, $totalCents - $taxCents));

        $amounts = TaxCalculator::fromStripeAmounts(
            $subtotalCents,
            $taxCents,
            $totalCents,
            $subscription?->tax_percent ? (float) $subscription->tax_percent : null,
        );

        $line = $stripeInvoice->lines->data[0] ?? null;
        $planName = $line?->description ?? $subscription?->plan_name ?? 'Premium Subscription';

        $pi = $stripeInvoice->payment_intent ?? null;
        $piId = is_string($pi) ? $pi : ($pi?->id ?? $subscriptionInvoice?->payment_intent_id);

        return $this->createAndFinalize([
            'user_id' => $user->id,
            'subscription_id' => $subscription?->id,
            'subscription_invoice_id' => $subscriptionInvoice?->id,
            'order_id' => $stripeInvoice->number ?? $stripeInvoice->id,
            'stripe_payment_intent' => $piId,
            'stripe_session_id' => null,
            'stripe_invoice_id' => $stripeInvoice->id,
            'stripe_subscription_id' => $stripeSubId,
            'amount' => $amounts['subtotal'],
            'tax_amount' => $amounts['tax_amount'],
            'total_amount' => $amounts['total'],
            'currency' => $stripeInvoice->currency ?? 'eur',
            'payment_status' => $stripeInvoice->status === 'paid' ? Invoice::PAYMENT_PAID : Invoice::PAYMENT_PENDING,
            'status' => $stripeInvoice->status === 'paid' ? Invoice::STATUS_PAID : Invoice::STATUS_ISSUED,
            'billing_name' => $stripeInvoice->customer_name ?? $subscription?->customer_name ?? $user->name,
            'billing_email' => $stripeInvoice->customer_email ?? $user->email,
            'billing_address' => $this->formatBillingAddress($user, $stripeInvoice->customer_address ?? null),
            'product_description' => $this->productDescription(
                $planName,
                $subscription?->billing_interval ?? 'subscription',
                $subscription,
                $stripeInvoice->period_start ?? null,
                $stripeInvoice->period_end ?? null,
            ),
            'plan_interval' => $subscription?->billing_interval,
            'quantity' => (int) ($line?->quantity ?? 1),
            'payment_method' => 'Stripe',
            'payment_method_brand' => $subscription?->payment_method_brand,
            'payment_method_last4' => $subscription?->payment_method_last4,
            'paid_at' => $stripeInvoice->status === 'paid' ? now() : null,
            'metadata' => ['source' => 'stripe.invoice.paid'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createAndFinalize(array $attributes): Invoice
    {
        return DB::transaction(function () use ($attributes) {
            $attributes['invoice_number'] = $attributes['invoice_number'] ?? $this->generateInvoiceNumber();

            $invoice = Invoice::query()->create($attributes);

            $path = $this->generatePdf($invoice);
            $invoice->update(['invoice_pdf_path' => $path]);

            $this->queueInvoiceEmail($invoice->fresh());

            Log::info('Invoice issued', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'user_id' => $invoice->user_id,
            ]);

            return $invoice->fresh();
        });
    }

    public function generateInvoiceNumber(): string
    {
        $prefix = config('invoice.number_prefix', 'INV');
        $date = now()->format('Ymd');

        $latest = Invoice::query()
            ->where('invoice_number', 'like', "{$prefix}-{$date}-%")
            ->orderByDesc('id')
            ->value('invoice_number');

        $sequence = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $m)) {
            $sequence = (int) $m[1] + 1;
        }

        return sprintf('%s-%s-%06d', $prefix, $date, $sequence);
    }

    public function generatePdf(Invoice $invoice): string
    {
        $disk = config('invoice.storage_disk', 'public');
        $directory = trim(config('invoice.storage_directory', 'invoices'), '/');
        $filename = $invoice->invoice_number.'.pdf';
        $relativePath = $directory.'/'.$filename;

        Storage::disk($disk)->makeDirectory($directory);

        $pdf = Pdf::loadView('pdf.invoice', $this->pdfViewData($invoice))
            ->setPaper('a4')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans');

        Storage::disk($disk)->put($relativePath, $pdf->output());

        return $relativePath;
    }

    /**
     * @return array<string, mixed>
     */
    public function pdfViewData(Invoice $invoice): array
    {
        $company = config('invoice.company');
        $logoPath = public_path($company['logo_path'] ?? '');
        $logoDataUri = null;
        if (is_string($logoPath) && $logoPath !== '' && is_file($logoPath)) {
            $mime = mime_content_type($logoPath) ?: 'image/png';
            $logoDataUri = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($logoPath));
        }

        return [
            'invoice' => $invoice,
            'company' => $company,
            'logoDataUri' => $logoDataUri,
            'subtotalFormatted' => MoneyFormatter::format($invoice->amount, $invoice->currency),
            'taxFormatted' => MoneyFormatter::format($invoice->tax_amount, $invoice->currency),
            'totalFormatted' => MoneyFormatter::format($invoice->total_amount, $invoice->currency),
            'currencyUpper' => strtoupper($invoice->currency),
            'paymentMethodLabel' => $this->paymentMethodLabel($invoice),
            'periodLabel' => $this->periodLabel($invoice),
        ];
    }

    public function ensurePdfAndEmail(Invoice $invoice): void
    {
        if (! $invoice->invoice_pdf_path || ! Storage::disk(config('invoice.storage_disk', 'public'))->exists($invoice->invoice_pdf_path)) {
            $path = $this->generatePdf($invoice);
            $invoice->update(['invoice_pdf_path' => $path]);
        }

        if ($invoice->emailed_at === null) {
            $this->queueInvoiceEmail($invoice->fresh());
        }
    }

    public function queueInvoiceEmail(Invoice $invoice): void
    {
        SendInvoiceEmailJob::dispatch($invoice->id)->onQueue(config('invoice.queue', 'emails'));
    }

    private function paymentMethodLabel(Invoice $invoice): string
    {
        if ($invoice->payment_method_brand && $invoice->payment_method_last4) {
            return ucfirst($invoice->payment_method_brand).' •••• '.$invoice->payment_method_last4;
        }

        return $invoice->payment_method ?? 'Stripe / Card';
    }

    private function periodLabel(Invoice $invoice): ?string
    {
        $subscription = $invoice->subscription;
        if (! $subscription?->current_period_start || ! $subscription?->current_period_end) {
            return null;
        }

        return $subscription->current_period_start->format('M j, Y')
            .' – '
            .$subscription->current_period_end->format('M j, Y');
    }

    private function productDescription(
        string $planName,
        ?string $interval,
        ?Subscription $subscription = null,
        ?int $periodStart = null,
        ?int $periodEnd = null,
    ): string {
        $intervalLabel = match ($interval) {
            'monthly', 'month' => 'Monthly',
            'yearly', 'year' => 'Yearly',
            'one_time' => 'One-time',
            default => $interval ? ucfirst(str_replace('_', ' ', $interval)) : 'Premium',
        };

        $base = trim($planName).' — '.$intervalLabel.' plan';

        if ($periodStart && $periodEnd) {
            $start = \Carbon\Carbon::createFromTimestamp($periodStart)->format('M j, Y');
            $end = \Carbon\Carbon::createFromTimestamp($periodEnd)->format('M j, Y');

            return $base." ({$start} – {$end})";
        }

        if ($subscription?->current_period_start && $subscription?->current_period_end) {
            return $base.' ('.$subscription->current_period_start->format('M j, Y')
                .' – '.$subscription->current_period_end->format('M j, Y').')';
        }

        return $base;
    }

    private function formatBillingAddress(User $user, mixed $stripeAddress = null): ?string
    {
        if (is_object($stripeAddress)) {
            $parts = array_filter([
                $stripeAddress->line1 ?? null,
                $stripeAddress->line2 ?? null,
                trim(($stripeAddress->postal_code ?? '').' '.($stripeAddress->city ?? '')),
                $stripeAddress->state ?? null,
                $stripeAddress->country ?? null,
            ]);

            if ($parts !== []) {
                return implode("\n", $parts);
            }
        }

        $parts = array_filter([
            $user->address,
            $user->country,
        ]);

        return $parts !== [] ? implode("\n", $parts) : null;
    }
}
