<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get email verification setting from system settings
        $emailVerificationMode = SystemSetting::get('email_verification', 'optional');

        // If email verification is disabled or optional, allow all requests
        if ($emailVerificationMode !== 'required') {
            return $next($request);
        }

        // Get authenticated user
        $user = $request->user();

        // If user is not authenticated, allow (auth middleware will handle this)
        if (! $user) {
            return $next($request);
        }

        // If user is a guest, allow (guests don't have emails to verify)
        if ($user->is_guest) {
            return $next($request);
        }

        // If user's email is not verified, return error
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('auth.email_not_verified'),
                'error' => 'email_not_verified',
                'email_verification_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
