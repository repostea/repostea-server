<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class UserStatusMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check authenticated users
        if ($request->user()) {
            $user = $request->user();

            // Allow admins to bypass this check
            if ($user->isAdmin()) {
                return $next($request);
            }

            // Check if user status is pending
            if ($user->status === 'pending') {
                return response()->json([
                    'message' => 'Your account is pending approval. Please wait for an administrator to review your registration.',
                    'status' => 'pending',
                ], 403);
            }

            // Check if user status is rejected
            if ($user->status === 'rejected') {
                return response()->json([
                    'message' => 'Your account registration has been rejected. Please contact support for more information.',
                    'status' => 'rejected',
                ], 403);
            }
        }

        return $next($request);
    }
}
