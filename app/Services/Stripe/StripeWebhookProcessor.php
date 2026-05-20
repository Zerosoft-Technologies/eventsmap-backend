<?php

namespace App\Services\Stripe;

use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PremiumNotificationService;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Webhook;

class StripeWebhookProcessor
{
    public function __construct(
        private readonly SubscriptionPersistService $persistService,
        private readonly SubscriptionUserStateService $userState,
        private readonly StripeWebhookSecretResolver $webhookSecretResolver,
        private readonly InvoiceService $invoiceService,
        private readonly PremiumNotificationService $premiumNotifications,
    ) {}

    public function verifyAndParseEvent(string $payload, ?string $signatureHeader): Event
    {
        $secret = $this->webhookSecretResolver->resolve();
        if ($secret === null || $secret === '') {
            throw new \RuntimeException('Stripe webhook secret is not configured.');
        }

        if ($signatureHeader === null || $signatureHeader === '') {
            throw new \InvalidArgumentException('Missing Stripe-Signature header.');
        }

        return Webhook::constructEvent($payload, $signatureHeader, $secret);
    }

    public function process(Event $event, string $rawPayload): void
    {
        $decoded = json_decode($rawPayload, true);
        if (! is_array($decoded)) {
            throw new \UnexpectedValueException('Invalid webhook JSON.');
        }

        $log = SubscriptionEvent::query()->firstOrCreate(
            ['stripe_event_id' => $event->id],
            [
                'event_type' => $event->type,
                'api_version' => $event->api_version,
                'user_id' => null,
                'subscription_id' => null,
                'livemode' => (bool) $event->livemode,
                'payload' => $decoded,
            ],
        );

        if ($log->processed_at !== null && $log->processing_error === null) {
            return;
        }

        try {
            $this->dispatch($event);
            $log->update([
                'processed_at' => now(),
                'processing_error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook processing failed', [
                'event_id' => $event->id,
                'type' => $event->type,
                'error' => $e->getMessage(),
            ]);
            $log->update([
                'processing_error' => $e->getMessage(),
                'processed_at' => now(),
            ]);
        }
    }

    private function dispatch(Event $event): void
    {
        match ($event->type) {
            'checkout.session.completed' => $this->onCheckoutSessionCompleted($event),
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->onSubscriptionLifecycle($event),
            'invoice.paid',
            'invoice.payment_succeeded' => $this->onInvoicePaid($event),
            'invoice.payment_failed' => $this->onInvoicePaymentFailed($event),
            'customer.updated' => $this->onCustomerUpdated($event),
            default => null,
        };
    }

    private function onCheckoutSessionCompleted(Event $event): void
    {
        $session = $event->data->object;
        $customerId = $this->normalizeStripeId($session->customer);

        $user = $this->findUserForStripeCustomer($customerId);
        if (! $user && isset($session->metadata['user_id'])) {
            $user = User::query()->find((int) $session->metadata['user_id']);
        }
        if (! $user) {
            Log::warning('checkout.session.completed: user not found', ['session' => $session->id]);

            return;
        }

        $subscription = $this->persistService->syncFromCheckoutSession($session, $user);

        $user->refresh();

        if ($session->payment_status === 'paid') {
            try {
                $this->invoiceService->issueFromCheckoutSession($session, $user, $subscription);
            } catch (\Throwable $e) {
                Log::error('Invoice generation failed after checkout', [
                    'session_id' => $session->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $this->premiumNotifications->sendPremiumWelcomeIfNeeded($user->fresh());
            }
        }

        $patch = [
            'email_verified_at' => $user->email_verified_at ?? now(),
        ];
        if (is_string($customerId) && $customerId !== '') {
            $patch['stripe_customer_id'] = $customerId;
        }
        $user->update($patch);
    }

    private function onSubscriptionLifecycle(Event $event): void
    {
        $sub = $event->data->object;
        $this->persistService->persistStripeSubscription($sub, null);
    }

    private function onInvoicePaid(Event $event): void
    {
        $invoice = $event->data->object;
        $subscriptionInvoice = $this->persistService->upsertInvoiceFromStripe($invoice);

        $user = $this->findUserForStripeCustomer($this->normalizeStripeId($invoice->customer));
        if ($user) {
            $this->userState->applyPaymentSucceeded($user);

            if ($invoice->status === 'paid' || (int) ($invoice->amount_paid ?? 0) > 0) {
                try {
                    $subscription = $subscriptionInvoice?->subscription_id
                        ? Subscription::query()->find($subscriptionInvoice->subscription_id)
                        : null;
                    $this->invoiceService->issueFromStripeInvoice($invoice, $user, $subscriptionInvoice, $subscription);
                } catch (\Throwable $e) {
                    Log::error('Invoice generation failed after stripe invoice.paid', [
                        'stripe_invoice_id' => $invoice->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private function onInvoicePaymentFailed(Event $event): void
    {
        $invoice = $event->data->object;
        $this->persistService->upsertInvoiceFromStripe($invoice);

        $user = $this->findUserForStripeCustomer($this->normalizeStripeId($invoice->customer));
        if ($user) {
            $this->userState->applyPaymentFailed($user);
        }
    }

    private function onCustomerUpdated(Event $event): void
    {
        $customer = $event->data->object;
        $this->persistService->updateSubscriptionsForCustomer($customer);
    }

    private function findUserForStripeCustomer(?string $customerId): ?User
    {
        if (! is_string($customerId) || $customerId === '') {
            return null;
        }

        return User::query()->where('stripe_customer_id', $customerId)->first();
    }

    private function normalizeStripeId(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }
        if (is_object($value) && isset($value->id) && is_string($value->id)) {
            return $value->id;
        }

        return null;
    }
}
