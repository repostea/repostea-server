<?php

declare(strict_types=1);

namespace App\Helpers;

use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

final class RateLimitHelper
{
    /**
     * Check if a user or IP has exceeded the rate limit for a specific action.
     *
     * @param  string  $action  The action being checked (e.g., 'create_post')
     * @param  int|null  $userId  The user ID (null for guest/IP-based checking)
     * @param  string|null  $ipAddress  The IP address (used if userId is null)
     *
     * @return array ['exceeded' => bool, 'remaining' => int, 'retry_after' => int|null]
     */
    public static function check(string $action, ?int $userId = null, ?string $ipAddress = null): array
    {
        $actionConfig = config("rate_limits.actions.{$action}");

        if (! $actionConfig) {
            return ['exceeded' => false, 'remaining' => 999, 'retry_after' => null];
        }

        $maxAttempts = $actionConfig['max_attempts'];
        $decayMinutes = $actionConfig['decay_minutes'];

        // Apply karma multiplier if user exists
        if ($userId && ($actionConfig['use_karma_multiplier'] ?? false)) {
            $user = \App\Models\User::find($userId);
            if ($user) {
                $maxAttempts = self::applyKarmaMultiplier($maxAttempts, $user->karma_points ?? 0);
            }
        }

        // Build cache key
        $key = self::buildCacheKey($action, $userId, $ipAddress);

        // Get current attempts
        $attempts = (int) Cache::get($key, 0);

        $exceeded = $attempts >= $maxAttempts;
        $remaining = max(0, $maxAttempts - $attempts);
        $retryAfter = $exceeded ? self::getRetryAfter($key, $decayMinutes) : null;

        return [
            'exceeded' => $exceeded,
            'remaining' => $remaining,
            'retry_after' => $retryAfter,
            'max_attempts' => $maxAttempts,
            'current_attempts' => $attempts,
        ];
    }

    /**
     * Manually increment the rate limit counter for an action
     * Useful for tracking actions that don't go through the middleware.
     *
     * @return int The new attempt count
     */
    public static function increment(string $action, ?int $userId = null, ?string $ipAddress = null): int
    {
        $actionConfig = config("rate_limits.actions.{$action}");

        if (! $actionConfig) {
            return 0;
        }

        $decayMinutes = $actionConfig['decay_minutes'];
        $key = self::buildCacheKey($action, $userId, $ipAddress);

        $attempts = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, now()->addMinutes($decayMinutes));

        return $attempts;
    }

    /**
     * Manually reset the rate limit counter for an action
     * Useful for testing or admin overrides.
     */
    public static function reset(string $action, ?int $userId = null, ?string $ipAddress = null): bool
    {
        $key = self::buildCacheKey($action, $userId, $ipAddress);

        return Cache::forget($key);
    }

    /**
     * Get the current attempt count for an action.
     */
    public static function getAttempts(string $action, ?int $userId = null, ?string $ipAddress = null): int
    {
        $key = self::buildCacheKey($action, $userId, $ipAddress);

        return (int) Cache::get($key, 0);
    }

    /**
     * Get time until rate limit reset in seconds.
     */
    public static function getTimeUntilReset(string $action, ?int $userId = null, ?string $ipAddress = null): ?int
    {
        $actionConfig = config("rate_limits.actions.{$action}");
        if (! $actionConfig) {
            return null;
        }

        $key = self::buildCacheKey($action, $userId, $ipAddress);

        return self::getRetryAfter($key, $actionConfig['decay_minutes']);
    }

    /**
     * Build a unique cache key for rate limiting.
     */
    protected static function buildCacheKey(string $action, ?int $userId = null, ?string $ipAddress = null): string
    {
        if ($userId) {
            return "rate_limit:{$action}:user:{$userId}";
        }

        if ($ipAddress) {
            return "rate_limit:{$action}:ip:{$ipAddress}";
        }

        // Fallback to a generic key (not recommended in production)
        return "rate_limit:{$action}:anonymous";
    }

    /**
     * Apply karma-based multiplier to max attempts.
     */
    protected static function applyKarmaMultiplier(int $baseLimit, int $karma): int
    {
        $multipliers = config('rate_limits.karma_multipliers', [
            0 => 1.0,
            100 => 1.5,
            500 => 2.0,
            1000 => 2.5,
            5000 => 3.0,
            10000 => 4.0,
        ]);

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
    protected static function getRetryAfter(string $key, int $decayMinutes): int
    {
        // Try to get exact expiration time if stored
        $expiresAt = Cache::get($key . ':expires');

        if ($expiresAt instanceof \Illuminate\Support\Carbon) {
            return max(1, $expiresAt->diffInSeconds(now()));
        }

        if ($expiresAt instanceof DateTimeInterface) {
            return max(1, (int) abs(now()->getTimestamp() - $expiresAt->getTimestamp()));
        }

        // Fallback to decay minutes
        return $decayMinutes * 60;
    }

    /**
     * Get rate limit configuration for all actions.
     */
    public static function getAllLimits(): array
    {
        return config('rate_limits.actions', []);
    }

    /**
     * Get rate limit configuration for a specific action.
     */
    public static function getLimit(string $action): ?array
    {
        return config("rate_limits.actions.{$action}");
    }

    /**
     * Check if IP is blacklisted.
     *
     * @return array ['blacklisted' => bool, 'reason' => string|null, 'blacklisted_at' => string|null]
     */
    public static function isIpBlacklisted(string $ipAddress): array
    {
        $key = "ip_blacklist:{$ipAddress}";
        $data = Cache::get($key);

        if (! $data) {
            return ['blacklisted' => false, 'reason' => null, 'blacklisted_at' => null];
        }

        return [
            'blacklisted' => true,
            'reason' => $data['reason'] ?? 'No reason provided',
            'blacklisted_at' => $data['blacklisted_at'] ?? null,
            'blacklisted_by' => $data['blacklisted_by'] ?? null,
        ];
    }
}
