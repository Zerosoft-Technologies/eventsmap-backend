<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PremiumNotificationService;
use App\Services\Stripe\PremiumCheckoutSessionFactory;
use App\Services\Stripe\StripeWebhookProcessor;
use App\Services\Stripe\SubscriptionPersistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;

class StripeController extends Controller
{
    public function __construct(
        private readonly SubscriptionPersistService $subscriptionPersist,
        private readonly StripeWebhookProcessor $webhookProcessor,
        private readonly PremiumCheckoutSessionFactory $premiumCheckout,
        private readonly InvoiceService $invoiceService,
        private readonly PremiumNotificationService $premiumNotifications,
    ) {}

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
            if (! is_string($stripeSecret) || $stripeSecret === '') {
                $stripeSecret = env('STRIPE_SECRET');
            }

            if (! is_string($stripeSecret) || $stripeSecret === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe is not configured on the server.',
                ], 500);
            }

            $stripe = new StripeClient($stripeSecret);

            $session = $stripe->checkout->sessions->retrieve(
                $request->input('session_id'),
                ['expand' => ['subscription', 'payment_intent', 'line_items']]
            );

            if ($session->payment_status !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment has not been completed.',
                ], 400);
            }

            $customerId = is_string($session->customer) ? $session->customer : ($session->customer->id ?? null);

            $user = User::query()
                ->where('stripe_session_id', $session->id)
                ->when(is_string($customerId), fn ($q) => $q->orWhere('stripe_customer_id', $customerId))
                ->first();

            if (! $user) {
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
                } elseif ($user->account_type === User::ACCOUNT_PREMIUM && ! $user->premium_started_at) {
                    $updateData['premium_started_at'] = now();
                }
                if (! empty($updateData)) {
                    $user->update($updateData);
                    $user->refresh();
                }

                try {
                    $subscription = $this->subscriptionPersist->syncFromCheckoutSession($session, $user);
                    $this->invoiceService->issueFromCheckoutSession($session, $user, $subscription);
                } catch (\Throwable $e) {
                    Log::error('Subscription persist failed after verify (active user)', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->premiumNotifications->sendPremiumWelcomeIfNeeded($user->fresh());
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
                'stripe_customer_id' => is_string($customerId) ? $customerId : $user->stripe_customer_id,
                'stripe_subscription_id' => $stripeSubscriptionId,
                'stripe_session_id' => $session->id,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ];
            if ($user->account_type === User::ACCOUNT_FREE) {
                $updateData['account_type'] = User::ACCOUNT_PREMIUM;
                $updateData['premium_started_at'] = now();
            } elseif ($user->account_type === User::ACCOUNT_PREMIUM && ! $user->premium_started_at) {
                $updateData['premium_started_at'] = now();
            }
            $user->update($updateData);

            $user->refresh();

            try {
                $subscription = $this->subscriptionPersist->syncFromCheckoutSession($session, $user);
                $this->invoiceService->issueFromCheckoutSession($session, $user, $subscription);
            } catch (\Throwable $e) {
                Log::error('Subscription persist failed after verify', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $this->premiumNotifications->sendPremiumWelcomeIfNeeded($user->fresh());
            }

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
            $event = $this->webhookProcessor->verifyAndParseEvent($payload, $signature);
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
        } catch (\RuntimeException $e) {
            Log::error('Stripe webhook misconfiguration', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook is not configured on the server.',
            ], 500);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        try {
            $this->webhookProcessor->process($event, $payload);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook fatal error', ['error' => $e->getMessage()]);

            return response()->json(['received' => true]);
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

        if (! $user->stripe_customer_id) {
            return response()->json([
                'success' => false,
                'message' => 'No Stripe customer found. Please contact support.',
            ], 400);
        }

        try {
            $checkoutSession = $this->premiumCheckout->create(
                $user,
                [
                    'user_id' => (string) $user->id,
                ],
                config('app.frontend_url').'/payment/success?session_id={CHECKOUT_SESSION_ID}',
                config('app.frontend_url').'/payment/cancel',
                'Premium Account',
            );

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
