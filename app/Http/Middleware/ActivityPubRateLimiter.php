<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ActivityPubBlockedInstance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiter for ActivityPub inbox endpoints.
 *
 * This middleware provides rate limiting for incoming ActivityPub requests.
 * It rate-limits by remote instance domain to prevent abuse while allowing
 * legitimate federation traffic. Also checks for blocked instances.
 */
final class ActivityPubRateLimiter
{
    /**
     * Handle an incoming ActivityPub request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  int  $maxAttempts  Maximum requests per decay period (default: 300)
     * @param  int  $decayMinutes  Time window in minutes (default: 1)
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 300, int $decayMinutes = 1): Response
    {
        // Get the remote instance domain from the request
        $domain = $this->extractDomain($request);

        // Check if the instance is blocked
        if ($domain !== null && ActivityPubBlockedInstance::isBlocked($domain)) {
            Log::info('ActivityPub: Request from blocked instance rejected', [
                'domain' => $domain,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'error' => 'Instance is blocked.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($domain === null) {
            // If we can't determine the domain, use IP-based rate limiting
            $domain = 'ip:' . $request->ip();
        }

        $key = "activitypub:rate_limit:{$domain}";

        // Get current attempts
        $attempts = (int) Cache::get($key, 0);

        // Check if rate limit exceeded
        if ($attempts >= $maxAttempts) {
            Log::warning('ActivityPub: Rate limit exceeded', [
                'domain' => $domain,
                'ip' => $request->ip(),
                'attempts' => $attempts,
                'max_attempts' => $maxAttempts,
                'path' => $request->path(),
            ]);

            return response()->json([
                'error' => 'Rate limit exceeded. Please slow down.',
            ], Response::HTTP_TOO_MANY_REQUESTS)
                ->header('Retry-After', (string) ($decayMinutes * 60));
        }

        // Increment attempts counter
        Cache::put($key, $attempts + 1, now()->addMinutes($decayMinutes));

        // Allow the request
        $response = $next($request);

        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $maxAttempts - $attempts - 1));
        $response->headers->set('X-RateLimit-Reset', (string) now()->addMinutes($decayMinutes)->getTimestamp());

        return $response;
    }

    /**
     * Extract the remote instance domain from the ActivityPub request.
     */
    private function extractDomain(Request $request): ?string
    {
        // Try to get domain from the actor in the request body
        $body = $request->json()->all();
        $actor = $body['actor'] ?? null;

        if (is_string($actor) && $actor !== '') {
            $parsed = parse_url($actor);
            if (isset($parsed['host'])) {
                return $parsed['host'];
            }
        }

        // Try to get domain from HTTP Signature keyId
        $signature = $request->header('Signature');
        if ($signature !== null) {
            if (preg_match('/keyId="([^"]+)"/', $signature, $matches)) {
                $keyId = $matches[1];
                $parsed = parse_url($keyId);
                if (isset($parsed['host'])) {
                    return $parsed['host'];
                }
            }
        }

        return null;
    }
}
