<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class RegisterController extends Controller
{
    /**
     * POST /api/auth/register
     *
     * Handle free and premium account registration.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
            'profile_type' => 'required|string|in:event,talent,organizer,venue',
            'account_type' => 'required|string|in:free,premium',
        ]);

        if ($validated['account_type'] === 'free') {
            return $this->registerFree($request, $validated);
        }

        return $this->registerPremium($request, $validated);
    }

    /**
     * Register a free account user.
     */
    private function registerFree(Request $request, array $validated): JsonResponse
    {
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'profile_type' => $validated['profile_type'],
            'role' => User::ROLE_USER,
            'account_type' => User::ACCOUNT_FREE,
            'status' => User::STATUS_ACTIVE,
            'is_active' => true,
        ]);

        event(new Registered($user));

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please verify your email.',
        ], 201);
    }

    /**
     * Register a premium account user with Stripe Checkout.
     */
    private function registerPremium(Request $request, array $validated): JsonResponse
    {
        $premiumRules = [
            'billing_type' => 'required|string|in:private,business',
        ];

        $billingType = $request->input('billing_type');

        if ($billingType === 'private') {
            $premiumRules['full_name'] = 'required|string|max:255';
            $premiumRules['country'] = 'required|string|max:255';
            $premiumRules['address'] = 'required|string|max:255';
            $premiumRules['postal_code'] = 'required|string|max:20';
            $premiumRules['city'] = 'required|string|max:255';
        } elseif ($billingType === 'business') {
            $premiumRules['company_name'] = 'required|string|max:255';
            $premiumRules['vat_number'] = 'required|string|max:50';
            $premiumRules['country'] = 'required|string|max:255';
            $premiumRules['address'] = 'required|string|max:255';
            $premiumRules['postal_code'] = 'required|string|max:20';
            $premiumRules['city'] = 'required|string|max:255';
        }

        $premiumValidated = $request->validate($premiumRules);

        if ($billingType === 'business') {
            if (!preg_match('/^[A-Z]{2}[0-9A-Z]{2,13}$/', $premiumValidated['vat_number'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid VAT number format.',
                    'errors' => [
                        'vat_number' => ['The VAT number format is invalid. Expected EU format (e.g. DE123456789).'],
                    ],
                ], 422);
            }
        }

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => now(),
            'password' => $validated['password'],
            'profile_type' => $validated['profile_type'],
            'role' => User::ROLE_USER,
            'account_type' => User::ACCOUNT_PREMIUM,
            'status' => User::STATUS_PENDING_PAYMENT,
            'is_active' => true,
            'billing_type' => $premiumValidated['billing_type'],
            'country' => $premiumValidated['country'],
            'address' => $premiumValidated['address'],
            'postal_code' => $premiumValidated['postal_code'],
            'city' => $premiumValidated['city'],
        ];

        if ($billingType === 'private') {
            $userData['full_name'] = $premiumValidated['full_name'];
        } else {
            $userData['company_name'] = $premiumValidated['company_name'];
            $userData['vat_number'] = $premiumValidated['vat_number'];
        }

        $user = User::create($userData);

        try {
            $stripeSecret = config('services.stripe.secret');
            // When config is cached on live and env vars were changed,
            // `config()` can return null. Fallback to raw env value.
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

            $customer = $stripe->customers->create([
                'name' => $billingType === 'business'
                    ? $premiumValidated['company_name']
                    : $premiumValidated['full_name'],
                'email' => $validated['email'],
                'address' => [
                    'line1' => $premiumValidated['address'],
                    'city' => $premiumValidated['city'],
                    'postal_code' => $premiumValidated['postal_code'],
                    'country' => $premiumValidated['country'],
                ],
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);

            $user->update(['stripe_customer_id' => $customer->id]);

            $checkoutSession = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'customer' => $customer->id,
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
                // 'automatic_tax' => ['enabled' => true], // Disabled - requires business address in Stripe settings
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
            $user->forceDelete();

            return response()->json([
                'success' => false,
                'message' => 'Payment setup failed. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
