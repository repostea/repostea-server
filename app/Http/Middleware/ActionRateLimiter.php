<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\RateLimitLog;
use Closure;
use DateTimeInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class ActionRateLimiter
{
    /**
     * Handle an incoming request and apply configurable rate limiting.
     *
     * This middleware provides flexible rate limiting for different actions.
     * It can be configured per-route with custom limits based on user reputation (karma).
     *
     * Usage examples:
     * - Route::post('/posts', ...)->middleware('action.rate.limit:create_post')
     * - Route::post('/comments', ...)->middleware('action.rate.limit:create_comment,20')
     * - Route::post('/vote', ...)->middleware('action.rate.limit:vote,100,60')
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  $action  The action being rate-limited (e.g., 'create_post', 'create_comment')
     * @param  int|null  $maxAttempts  Override the default max attempts from config
     * @param  int|null  $decayMinutes  Override the default decay minutes from config
     */
    public function handle(Request $request, Closure $next, string $action, ?int $maxAttempts = null, ?int $decayMinutes = null): Response
    {
        $user = $request->user();

        // Get action configuration from config file
        $actionConfig = config("rate_limits.actions.{$action}");

        if (! $actionConfig) {
            Log::warning("Rate limit action '{$action}' not configured. Allowing request.");

            return $next($request);
        }

        // Override config with middleware parameters if provided
        $maxAttempts = $maxAttempts ?? $actionConfig['max_attempts'];
        $decayMinutes = $decayMinutes ?? $actionConfig['decay_minutes'];

        // Apply karma-based multiplier if user is authenticated and has karma
        if ($user && $actionConfig['use_karma_multiplier'] ?? false) {
            $maxAttempts = $this->applyKarmaMultiplier($maxAttempts, $user->karma_points ?? 0);
        }

        // Build cache key
        $key = $this->buildCacheKey($action, $user, $request);

        // Get current attempts
        $attempts = (int) Cache::get($key, 0);

        // Check if rate limit exceeded
        if ($attempts >= $maxAttempts) {
            // Log the rate limit violation
            $this->logRateLimitViolation($action, $user, $request, $attempts, $maxAttempts);

            // Check if we should ban the user for repeated violations
            if ($user && ($actionConfig['auto_ban_threshold'] ?? 0) > 0) {
                $this->checkAndApplyAutoBan($action, $user);
            }

            return response()->json([
                'message' => __('rate_limits.too_many_attempts', ['action' => __('rate_limits.actions.' . $action)]),
                'error' => 'rate_limit_exceeded',
                'retry_after' => $this->getRetryAfter($key, $decayMinutes),
                'max_attempts' => $maxAttempts,
                'decay_minutes' => $decayMinutes,
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Increment attempts counter
        Cache::put($key, $attempts + 1, now()->addMinutes($decayMinutes));

        // Allow the request
        $response = $next($request);

        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $maxAttempts - $attempts - 1));

        return $response;
    }

    /**
     * Build a unique cache key for rate limiting.
     */
    protected function buildCacheKey(string $action, $user, Request $request): string
    {
        if ($user) {
            return "rate_limit:{$action}:user:{$user->id}";
        }

        // For guest users, use IP address
        return "rate_limit:{$action}:ip:" . $request->ip();
    }

    /**
     * Apply karma-based multiplier to max attempts
     * Users with higher karma get more lenient rate limits.
     */
    protected function applyKarmaMultiplier(int $baseLimit, int $karma): int
    {
        $multipliers = config('rate_limits.karma_multipliers', [
            0 => 1.0,      // 0-99 karma: 1x (base limit)
            100 => 1.5,    // 100-499 karma: 1.5x
            500 => 2.0,    // 500-999 karma: 2x
            1000 => 2.5,   // 1000-4999 karma: 2.5x
            5000 => 3.0,   // 5000+ karma: 3x
        ]);

        // Find the applicable multiplier
        $multiplier = 1.0;
        foreach ($multipliers as $threshold => $mult) {
            if ($karma >= $threshold) {
                $multiplier = $mult;
            }
        }

        return (int) ceil($baseLimit * $multiplier);
    }

    /**
     * Calculate retry after time in seconds.
     */
    protected function getRetryAfter(string $key, int $decayMinutes): int
    {
        $expiresAt = Cache::get($key . ':expires', now()->addMinutes($decayMinutes));

        if ($expiresAt instanceof \Illuminate\Support\Carbon) {
            return max(1, $expiresAt->diffInSeconds(now()));
        }

        if ($expiresAt instanceof DateTimeInterface) {
            return max(1, (int) abs(now()->getTimestamp() - $expiresAt->getTimestamp()));
        }

        return $decayMinutes * 60;
    }

    /**
     * Log rate limit violation for monitoring.
     */
    protected function logRateLimitViolation(string $action, $user, Request $request, int $attempts, int $maxAttempts): void
    {
        try {
            RateLimitLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'attempts' => $attempts,
                'max_attempts' => $maxAttempts,
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'metadata' => [
                    'headers' => $request->headers->all(),
                    'referer' => $request->header('referer'),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Failed to log rate limit violation', [
                'error' => $e->getMessage(),
                'action' => $action,
                'user_id' => $user?->id,
            ]);
        }
    }

    /**
     * Check if user should be auto-banned for repeated violations.
     */
    protected function checkAndApplyAutoBan(string $action, $user): void
    {
        $actionConfig = config("rate_limits.actions.{$action}");
        $threshold = $actionConfig['auto_ban_threshold'] ?? 0;

        if ($threshold <= 0) {
            return;
        }

        // Count violations in the last 24 hours
        $recentViolations = RateLimitLog::where('user_id', $user->id)
            ->where('action', $action)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($recentViolations >= $threshold) {
            // Check if user is already banned
            if (! $user->isBanned()) {
                $banDuration = $actionConfig['auto_ban_duration_hours'] ?? 24;

                \App\Models\UserBan::create([
                    'user_id' => $user->id,
                    'banned_by' => null, // System ban
                    'type' => 'temporary',
                    'reason' => __('Automatic ban: Too many rate limit violations for :action', ['action' => $action]),
                    'internal_notes' => "Auto-banned after {$recentViolations} violations in 24 hours",
                    'expires_at' => now()->addHours($banDuration),
                    'is_active' => true,
                ]);

                Log::warning('User auto-banned for rate limit violations', [
                    'user_id' => $user->id,
                    'action' => $action,
                    'violations' => $recentViolations,
                    'ban_duration_hours' => $banDuration,
                ]);
            }
        }
    }
}
