<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeController extends Controller
{
    /**
     * POST /api/payment/verify
     *
     * Verify a Stripe Checkout session after payment.
     * NO auth middleware.
     */
    public function verifySession(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        try {
            $stripeSecret = config('services.stripe.secret');
            // Fallback in case config cache is stale.
            if (!is_string($stripeSecret) || $stripeSecret === '') {
                $stripeSecret = env('STRIPE_SECRET');
            }

            if (!is_string($stripeSecret) || $stripeSecret === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe is not configured on the server.',
                ], 500);
            }

            $stripe = new StripeClient($stripeSecret);

            $session = $stripe->checkout->sessions->retrieve(
                $request->input('session_id'),
                ['expand' => ['subscription', 'payment_intent']]
            );

            if ($session->payment_status !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment has not been completed.',
                ], 400);
            }

            $user = User::where('stripe_session_id', $session->id)
                ->orWhere('stripe_customer_id', $session->customer)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found for this payment session.',
                ], 404);
            }

            $stripeSubscriptionId = null;
            if ($session->subscription) {
                $stripeSubscriptionId = is_string($session->subscription) ? $session->subscription : $session->subscription->id;
            } elseif ($session->payment_intent) {
                $stripeSubscriptionId = is_string($session->payment_intent) ? $session->payment_intent : $session->payment_intent->id;
            }

            if ($user->status === User::STATUS_ACTIVE) {
                // Already verified: still sync stripe_subscription_id / stripe_session_id / account_type if missing (backfill)
                $updateData = [];
                if (empty($user->stripe_subscription_id) && $stripeSubscriptionId) {
                    $updateData['stripe_subscription_id'] = $stripeSubscriptionId;
                }
                if (empty($user->stripe_session_id) && $session->id) {
                    $updateData['stripe_session_id'] = $session->id;
                }
                if ($user->account_type === User::ACCOUNT_FREE) {
                    $updateData['account_type'] = User::ACCOUNT_PREMIUM;
                    $updateData['premium_started_at'] = $user->premium_started_at ?? now();
                } elseif ($user->account_type === User::ACCOUNT_PREMIUM && !$user->premium_started_at) {
                    $updateData['premium_started_at'] = now();
                }
                if (!empty($updateData)) {
                    $user->update($updateData);
                    $user->refresh();
                }

                $token = $user->createToken('auth_token')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'message' => 'Payment already verified.',
                    'stripe_customer_id' => $user->stripe_customer_id,
                    'stripe_subscription_id' => $user->stripe_subscription_id,
                    'token' => $token,
                    'user' => $user,
                ]);
            }

            $updateData = [
                'status' => User::STATUS_ACTIVE,
                'stripe_customer_id' => $session->customer,
                'stripe_subscription_id' => $stripeSubscriptionId,
                'stripe_session_id' => $session->id,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ];
            if ($user->account_type === User::ACCOUNT_FREE) {
                $updateData['account_type'] = User::ACCOUNT_PREMIUM;
                $updateData['premium_started_at'] = now();
            } elseif ($user->account_type === User::ACCOUNT_PREMIUM && !$user->premium_started_at) {
                $updateData['premium_started_at'] = now();
            }
            $user->update($updateData);

            $user->refresh();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'stripe_customer_id' => $user->stripe_customer_id,
                'stripe_subscription_id' => $user->stripe_subscription_id,
                'token' => $token,
                'user' => $user,
            ]);
        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify payment session.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/webhook/stripe
     *
     * Handle Stripe webhook events.
     * NO auth, NO CSRF.
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature, 
                config('services.stripe.webhook_secret')
            );
        } catch (SignatureVerificationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook signature.',
            ], 400);
        } catch (\UnexpectedValueException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook payload.',
            ], 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                $user = User::where('stripe_customer_id', $session->customer)->first();

                if ($user) {
                    $stripeSubscriptionId = $session->subscription ?? $session->payment_intent;
                    $isUpgrade = isset($session->metadata->type) && $session->metadata->type === 'upgrade';

                    $updateData = [
                        'status' => User::STATUS_ACTIVE,
                        'stripe_subscription_id' => $stripeSubscriptionId,
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ];

                    // Handle upgrade from free to premium
                    if ($isUpgrade || $user->account_type === User::ACCOUNT_FREE) {
                        $updateData['account_type'] = User::ACCOUNT_PREMIUM;
                        $updateData['premium_started_at'] = now();
                    }

                    // For new premium registrations, also set premium_started_at
                    if ($user->account_type === User::ACCOUNT_PREMIUM && !$user->premium_started_at) {
                        $updateData['premium_started_at'] = now();
                    }

                    $user->update($updateData);

                    Log::info('Stripe checkout completed for user ' . $user->id . ($isUpgrade ? ' (upgrade)' : ''));
                }
                break;

            case 'invoice.payment_succeeded':
                $invoice = $event->data->object;
                $user = User::where('stripe_customer_id', $invoice->customer)->first();

                if ($user) {
                    $user->update([
                        'status' => User::STATUS_ACTIVE,
                    ]);
                }
                break;

            case 'invoice.payment_failed':
                $invoice = $event->data->object;
                $user = User::where('stripe_customer_id', $invoice->customer)->first();

                if ($user) {
                    $user->update([
                        'status' => User::STATUS_PENDING_PAYMENT,
                    ]);
                }
                break;
        }

        return response()->json(['received' => true]);
    }

    /**
     * POST /api/payment/retry
     *
     * Retry payment for premium users with pending_payment status.
     * Requires auth:sanctum.
     */
    public function retryPayment(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->account_type !== User::ACCOUNT_PREMIUM) {
            return response()->json([
                'success' => false,
                'message' => 'Retry payment is only available for premium accounts.',
            ], 403);
        }

        if ($user->status !== User::STATUS_PENDING_PAYMENT) {
            return response()->json([
                'success' => false,
                'message' => 'Payment retry is only available for accounts with pending payment.',
            ], 403);
        }

        if (!$user->stripe_customer_id) {
            return response()->json([
                'success' => false,
                'message' => 'No Stripe customer found. Please contact support.',
            ], 400);
        }

        try {
            $stripeSecret = config('services.stripe.secret');
            // Fallback in case config cache is stale.
            if (!is_string($stripeSecret) || $stripeSecret === '') {
                $stripeSecret = env('STRIPE_SECRET');
            }

            if (!is_string($stripeSecret) || $stripeSecret === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe is not configured on the server.',
                ], 500);
            }

            $stripe = new StripeClient($stripeSecret);

            $checkoutSession = $stripe->checkout->sessions->create([
                'customer' => $user->stripe_customer_id,
                'payment_method_types' => ['card'],
                'mode' => 'payment',
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => 'Premium Account',
                            ],
                            'unit_amount' => 100,
                        ],
                        'quantity' => 1,
                    ],
                ],
                'success_url' => config('app.frontend_url') . '/payment/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url') . '/payment/cancel',
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);

            $user->update(['stripe_session_id' => $checkoutSession->id]);

            return response()->json([
                'success' => true,
                'checkout_url' => $checkoutSession->url,
                'session_id' => $checkoutSession->id,
            ]);
        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment setup failed. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
