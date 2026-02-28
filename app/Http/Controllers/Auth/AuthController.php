<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendVerificationEmail;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/auth/register
     *
     * Register a new free account user.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
            'profile_type' => 'required|string|in:event,talent,organizer,venue',
        ]);

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

        // Dispatch email verification job to prevent API timeout
        SendVerificationEmail::dispatch($user);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please check your email to verify your account before logging in.',
            'data' => [
                'user' => $this->formatUser($user),
            ],
        ], 201);
    }

    /**
     * POST /api/auth/login
     *
     * Authenticate user and return token.
     * Requires email verification for successful login.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check email verification BEFORE any other checks
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'EMAIL_NOT_VERIFIED',
                    'message' => 'Your email is not verified. Please check your registered email and verify your account.',
                ],
            ], 403);
        }

        if ($user->status === User::STATUS_SUSPENDED) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_SUSPENDED',
                    'message' => 'Your account has been suspended. Please contact support.',
                ],
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_DISABLED',
                    'message' => 'Your account has been disabled.',
                ],
            ], 403);
        }

        // Revoke existing tokens and create new one
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->formatUser($user),
                'token' => $token,
            ],
        ]);
    }

    /**
     * POST /api/auth/logout
     *
     * Revoke current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Successfully logged out.',
            ],
        ]);
    }

    /**
     * GET /api/auth/me
     *
     * Get authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->formatUser($request->user()),
        ]);
    }

    /**
     * GET/POST /api/auth/email/verify/{id}/{hash}
     *
     * Verify user email address and redirect to frontend.
     */
    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            // Redirect to frontend with error
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
            return redirect($frontendUrl . '/auth/verification-failed?reason=invalid_link');
        }

        if ($user->hasVerifiedEmail()) {
            // Redirect to frontend with already verified status
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
            return redirect($frontendUrl . '/auth/already-verified');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        // Redirect to frontend with success
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
        return redirect($frontendUrl . '/auth/email-verified?email=' . urlencode($user->email));
    }

    /**
     * POST /api/auth/email/resend
     *
     * Resend email verification notification.
     * Works for both authenticated and non-authenticated users.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);

        // If user is authenticated, use their email
        if ($request->user()) {
            $user = $request->user();
            $email = $user->email;
        } else {
            // For non-authenticated users, find by email
            $email = $request->email;
            $user = User::where('email', $email)->first();
        }

        // Always return success to prevent email enumeration
        $successMessage = 'If an account with this email exists, a verification link has been sent.';

        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'This email is already verified. You can log in.',
            ]);
        }

        // Dispatch email verification job to prevent timeout
        // SendVerificationEmail::dispatch($user);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => $successMessage,
        ]);
    }

    /**
     * POST /api/auth/password/forgot
     *
     * Send password reset link.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent to your email.',
            ]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * POST /api/auth/password/reset
     *
     * Reset password using token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|string|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke all tokens
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully.',
            ]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Format user data for API response.
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'billing_type' => $user->billing_type,
            'vat_number' => $user->vat_number,
            'company_name' => $user->company_name,
            'vat_validated' => $user->vat_validated,
            'address' => $user->address,
            'profile_type' => $user->profile_type,
            'account_type' => $user->account_type,
            'status' => $user->status,
            'email_verified' => $user->hasVerifiedEmail(),
            'country' => $user->country,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
