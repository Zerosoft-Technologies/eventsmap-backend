<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePremiumIsActive
{
    /**
     * Handle an incoming request.
     *
     * Blocks premium users who have not completed payment.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($request->user()->requiresPayment()) {
            return response()->json([
                'message' => 'Premium payment not completed.',
                'status' => 'pending_payment',
            ], 403);
        }

        return $next($request);
    }
}
