<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckUserBanned
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->isBanned()) {
            // Get active ban
            $ban = $request->user()->bans()
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->first();

            $message = 'Your account has been banned.';

            if ($ban) {
                $message .= ' Reason: ' . $ban->reason;

                if ($ban->expires_at) {
                    $message .= ' This ban expires on ' . $ban->expires_at->format('d M Y H:i');
                }
            }

            // For API requests, return JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Account Banned',
                    'message' => $message,
                    'ban' => $ban ? [
                        'type' => $ban->type,
                        'reason' => $ban->reason,
                        'expires_at' => $ban->expires_at?->toIso8601String(),
                    ] : null,
                ], 403);
            }

            // For web requests, logout and redirect
            auth()->logout();

            return redirect('/')->with('error', $message);
        }

        return $next($request);
    }
}
