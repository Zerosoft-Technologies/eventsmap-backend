<?php

namespace App\Services\Stripe;

final class StripeWebhookSecretResolver
{
    public function resolve(): ?string
    {
        $secret = config('services.stripe.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            $secret = env('STRIPE_WEBHOOK_SECRET');
        }

        if (! is_string($secret) || $secret === '') {
            return null;
        }

        return $secret;
    }
}
