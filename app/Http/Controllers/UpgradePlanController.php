<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Stripe\PremiumCheckoutSessionFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class UpgradePlanController extends Controller
{
    public function __construct(
        private readonly PremiumCheckoutSessionFactory $premiumCheckout,
    ) {}

    /**
     * POST /api/user/upgrade-plan
     *
     * Upgrade from FREE to PREMIUM account.
     * Requires authentication.
     */
    public function upgrade(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user is already premium
        if ($user->account_type === User::ACCOUNT_PREMIUM) {
            return response()->json([
                'success' => false,
                'message' => 'You are already on the premium plan.',
            ], 400);
        }

        // Check if email is verified
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email before upgrading.',
            ], 403);
        }

        // Check if account is active
        if ($user->status !== User::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Your account must be active to upgrade.',
            ], 403);
        }

        // Validate billing information
        $billingType = $request->input('billing_type');

        $rules = [
            'billing_type' => 'required|string|in:private,business',
            'country' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'city' => 'required|string|max:255',
        ];

        if ($billingType === 'private') {
            $rules['full_name'] = 'required|string|max:255';
        } elseif ($billingType === 'business') {
            $rules['company_name'] = 'required|string|max:255';
            $rules['vat_number'] = 'required|string|max:50';
        }

        $validated = $request->validate($rules);

        // Validate VAT number format for business
        if ($billingType === 'business') {
            if (! preg_match('/^[A-Z]{2}[0-9A-Z]{2,13}$/', $validated['vat_number'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid VAT number format.',
                    'errors' => [
                        'vat_number' => ['The VAT number format is invalid. Expected EU format (e.g. DE123456789).'],
                    ],
                ], 422);
            }
        }

        // Update user billing information
        $billingData = [
            'billing_type' => $validated['billing_type'],
            'country' => $validated['country'],
            'address' => $validated['address'],
            'postal_code' => $validated['postal_code'],
            'city' => $validated['city'],
        ];

        if ($billingType === 'private') {
            $billingData['full_name'] = $validated['full_name'];
        } else {
            $billingData['company_name'] = $validated['company_name'];
            $billingData['vat_number'] = $validated['vat_number'];
        }

        $user->update($billingData);

        try {
            $stripeSecret = config('services.stripe.secret');
            // Fallback in case config cache is stale.
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

            // Create or retrieve Stripe customer
            if (! $user->stripe_customer_id) {
                $customer = $stripe->customers->create([
                    'name' => $billingType === 'business'
                        ? $validated['company_name']
                        : $validated['full_name'],
                    'email' => $user->email,
                    'address' => [
                        'line1' => $validated['address'],
                        'city' => $validated['city'],
                        'postal_code' => $validated['postal_code'],
                        'country' => $validated['country'],
                    ],
                    'metadata' => [
                        'user_id' => $user->id,
                        'upgrade' => 'true',
                    ],
                ]);

                $user->update(['stripe_customer_id' => $customer->id]);
            } else {
                // Update existing customer
                $stripe->customers->update($user->stripe_customer_id, [
                    'name' => $billingType === 'business'
                        ? $validated['company_name']
                        : $validated['full_name'],
                    'address' => [
                        'line1' => $validated['address'],
                        'city' => $validated['city'],
                        'postal_code' => $validated['postal_code'],
                        'country' => $validated['country'],
                    ],
                    'metadata' => [
                        'user_id' => $user->id,
                        'upgrade' => 'true',
                    ],
                ]);
            }

            $checkoutSession = $this->premiumCheckout->create(
                $user,
                [
                    'user_id' => (string) $user->id,
                    'type' => 'upgrade',
                ],
                config('app.frontend_url').'/payment/success?session_id={CHECKOUT_SESSION_ID}',
                config('app.frontend_url').'/event-settings?upgrade=cancelled',
                'Premium Account Upgrade',
            );

            $user->update(['stripe_session_id' => $checkoutSession->id]);

            return response()->json([
                'success' => true,
                'checkout_url' => $checkoutSession->url,
                'session_id' => $checkoutSession->id,
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe upgrade error for user '.$user->id.': '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Payment setup failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
