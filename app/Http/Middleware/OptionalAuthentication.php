<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class OptionalAuthentication
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        Auth::shouldUse('sanctum');
        $token = $request->bearerToken();
        if ($token !== null && $token !== '') {
            $user = Auth::guard('sanctum')->user();
            if ($user !== null) {
                Auth::guard('sanctum')->setUser($user);
            }
        }

        return $next($request);
    }
}
