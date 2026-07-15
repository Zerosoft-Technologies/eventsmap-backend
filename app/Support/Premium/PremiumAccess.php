<?php

namespace App\Support\Premium;

use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Shared premium entitlement checks (account type + payment status).
 *
 * Used by premium-only features so validation is not duplicated across controllers.
 */
final class PremiumAccess
{
    public static function isEntitled(User $user): bool
    {
        return $user->isPremiumAccount() && ! $user->requiresPayment();
    }

    /**
     * @return JsonResponse|null 403 response when not entitled; null when allowed
     */
    public static function denyResponseUnlessEntitled(User $user, string $featureMessage): ?JsonResponse
    {
        if (! $user->isPremiumAccount()) {
            return response()->json([
                'success' => false,
                'message' => $featureMessage,
            ], 403);
        }

        if ($user->requiresPayment()) {
            return response()->json([
                'success' => false,
                'message' => 'Premium payment not completed.',
                'status' => 'pending_payment',
            ], 403);
        }

        return null;
    }

    /**
     * Legacy gallery API envelope ({ status: 'error', message }).
     *
     * @return JsonResponse|null 403 response when not entitled; null when allowed
     */
    public static function denyLegacyResponseUnlessEntitled(User $user, string $message): ?JsonResponse
    {
        if (self::isEntitled($user)) {
            return null;
        }

        $payload = [
            'status' => 'error',
            'message' => $user->isPremiumAccount() && $user->requiresPayment()
                ? 'Premium payment not completed.'
                : $message,
        ];

        if ($user->isPremiumAccount() && $user->requiresPayment()) {
            $payload['payment_status'] = 'pending_payment';
        }

        return response()->json($payload, 403);
    }
}
