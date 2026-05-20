<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\PremiumNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendInvoiceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $invoiceId,
    ) {}

    public function handle(PremiumNotificationService $notifications): void
    {
        $invoice = Invoice::query()->with('user')->find($this->invoiceId);
        if (! $invoice) {
            return;
        }

        $notifications->sendInvoiceReceipt($invoice);

        $user = $invoice->user;
        if ($user) {
            $notifications->sendPremiumWelcomeIfNeeded($user);
        }
    }
}
