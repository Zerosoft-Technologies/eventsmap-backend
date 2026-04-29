<?php

namespace App\Services\Stripe;

use Stripe\StripeClient;

final class StripeClientFactory
{
    public function make(): StripeClient
    {
        $secret = config('services.stripe.secret');
        if (! is_string($secret) || $secret === '') {
            $secret = env('STRIPE_SECRET');
        }

        if (! is_string($secret) || $secret === '') {
            throw new \InvalidArgumentException('Stripe secret is not configured.');
        }

        return new StripeClient($secret);
    }
}
