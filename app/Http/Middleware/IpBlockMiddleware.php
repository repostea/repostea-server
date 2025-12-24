<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IpBlock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class IpBlockMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // Check if IP is blocked
        if (IpBlock::isIpBlocked($ip)) {
            $block = IpBlock::getBlockForIp($ip);

            if ($block) {
                // Record the hit
                $block->recordHit();

                // Log the blocked attempt
                Log::channel('security')->warning('Blocked IP attempted access', [
                    'ip' => $ip,
                    'block_id' => $block->id,
                    'reason' => $block->reason,
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'user_agent' => $request->userAgent(),
                    'timestamp' => now()->toDateTimeString(),
                ]);

                // Return 403 Forbidden
                return response()->json([
                    'error' => 'Access Forbidden',
                    'message' => 'Your IP address has been blocked from accessing this resource.',
                    'reason' => $block->reason,
                ], 403);
            }
        }

        return $next($request);
    }
}
