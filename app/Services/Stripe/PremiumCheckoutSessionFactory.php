<?php

namespace App\Services\Stripe;

use App\Models\User;
use Stripe\Checkout\Session;

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
            $params['mode'] = 'subscription';
            $params['line_items'] = [
                ['price' => $priceId, 'quantity' => 1],
            ];
        } else {
            $params['mode'] = 'payment';
            $params['line_items'] = [
                [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => $productTitle,
                        ],
                        'unit_amount' => 100,
                    ],
                    'quantity' => 1,
                ],
            ];
        }

        return $stripe->checkout->sessions->create($params);
    }
}
