<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class ViewRateLimiter
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/v1/posts/*/view')) {
            $ip = $request->ip();

            // If it's on blacklist or suspicious, mark to not count the view
            // but allow the request to continue without error
            if ($this->isBlacklisted($ip)) {
                $request->attributes->set('skip_view_count', true);
            } elseif ($this->isSuspicious($request)) {
                $this->logSuspiciousActivity($ip, $request);
                $this->addToBlacklist($ip);
                $request->attributes->set('skip_view_count', true);
            }
        }

        return $next($request);
    }

    private function isBlacklisted(string $ip): bool
    {
        return Cache::has('blacklist_ip_' . $ip);
    }

    private function addToBlacklist(string $ip): void
    {
        Cache::put('blacklist_ip_' . $ip, true, now()->addDay());
    }

    private function isSuspicious(Request $request): bool
    {
        // Only detect bots, don't count requests (the cooldown is in ViewService)
        $userAgent = $request->header('User-Agent', '');
        if (empty($userAgent) ||
            str_contains(strtolower($userAgent), 'bot') ||
            str_contains(strtolower($userAgent), 'crawler') ||
            str_contains(strtolower($userAgent), 'scraper')) {
            return true;
        }

        return false;
    }

    private function logSuspiciousActivity(string $ip, Request $request): void
    {
        Log::channel('security')->warning('Suspicious view activity detected', [
            'ip' => $ip,
            'user_agent' => $request->header('User-Agent'),
            'referrer' => $request->header('Referer'),
            'post_id' => $request->route('post'),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}
