<?php

namespace App\Services\Stripe;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

final class PremiumCheckoutSessionFactory
{
    public function __construct(
        private readonly StripeClientFactory $clientFactory,
    ) {}

    /**
     * @param  array<string, string>  $metadata
     */
    public function create(User $user, array $metadata, string $successUrl, string $cancelUrl, string $productTitle = 'Premium Account'): Session
    {
        $stripe = $this->clientFactory->make();
        $priceId = config('services.stripe.price_id');

        $params = [
            'customer' => $user->stripe_customer_id,
            'payment_method_types' => ['card'],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
        ];

        if (is_string($priceId) && $priceId !== '') {
            $mode = $this->resolveCheckoutMode($stripe, $priceId);
            $params['mode'] = $mode;
            $params['line_items'] = [
                ['price' => $priceId, 'quantity' => 1],
            ];
        } else {
            $params['mode'] = 'payment';
            $params['line_items'] = [
                [
                    'price_data' => [
                        'currency' => config('services.stripe.premium_currency', 'eur'),
                        'product_data' => [
                            'name' => $productTitle,
                        ],
                        'unit_amount' => (int) config('services.stripe.premium_amount', 100),
                    ],
                    'quantity' => 1,
                ],
            ];
        }

        return $stripe->checkout->sessions->create($params);
    }

    /**
     * Subscription mode requires a recurring Price; one-time Prices must use payment mode.
     */
    private function resolveCheckoutMode(StripeClient $stripe, string $priceId): string
    {
        $override = strtolower((string) config('services.stripe.checkout_mode', 'auto'));
        if (in_array($override, ['subscription', 'payment'], true)) {
            return $override;
        }

        try {
            $price = $stripe->prices->retrieve($priceId);

            return $price->recurring !== null ? 'subscription' : 'payment';
        } catch (ApiErrorException $e) {
            Log::warning('Could not retrieve Stripe price; defaulting checkout to payment mode', [
                'price_id' => $priceId,
                'error' => $e->getMessage(),
            ]);

            return 'payment';
        }
    }
}
