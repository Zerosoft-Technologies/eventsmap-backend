<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ChatRateLimitMiddleware
{
    private const RATE_LIMIT_KEY_PREFIX = 'chat_rate_limit:';
    private const MAX_MESSAGES_PER_MINUTE = 20;
    private const WINDOW_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        $eventId = $request->route('event_id');
        if (!$eventId) {
            return $next($request);
        }

        $key = self::RATE_LIMIT_KEY_PREFIX . $user->id . ':' . $eventId;
        $timestamps = Cache::get($key, []);

        $now = time();
        $windowStart = $now - self::WINDOW_SECONDS;

        $timestamps = array_filter($timestamps, fn($ts) => $ts > $windowStart);

        if (count($timestamps) >= self::MAX_MESSAGES_PER_MINUTE) {
            $oldestTimestamp = min($timestamps);
            $retryAfter = $oldestTimestamp + self::WINDOW_SECONDS - $now;

            return response()->json([
                'success' => false,
                'message' => 'Rate limit exceeded. Please wait before sending more messages.',
                'retry_after' => max(1, $retryAfter),
            ], 429)->header('Retry-After', max(1, $retryAfter));
        }

        $timestamps[] = $now;
        Cache::put($key, $timestamps, self::WINDOW_SECONDS + 10);

        return $next($request);
    }
}
