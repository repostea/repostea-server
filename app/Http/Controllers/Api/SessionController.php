<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class SessionController extends Controller
{
    /**
     * List all active sessions for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()->id;

        $sessions = PersonalAccessToken::where('tokenable_type', get_class($user))
            ->where('tokenable_id', $user->id)
            ->orderByDesc('last_used_at')
            ->get(['id', 'name', 'last_used_at', 'created_at', 'ip_address', 'user_agent'])
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'device' => $this->parseUserAgent($token->user_agent),
                'ip_address' => $token->ip_address,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'is_current' => $token->id === $currentTokenId,
            ]);

        return response()->json([
            'sessions' => $sessions,
        ]);
    }

    /**
     * Revoke a specific session.
     */
    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()->id;

        if ($tokenId === $currentTokenId) {
            return response()->json([
                'message' => __('messages.sessions.cannot_revoke_current'),
            ], 400);
        }

        $deleted = PersonalAccessToken::where('id', $tokenId)
            ->where('tokenable_type', get_class($user))
            ->where('tokenable_id', $user->id)
            ->delete();

        if ($deleted === 0) {
            return response()->json([
                'message' => __('messages.sessions.not_found'),
            ], 404);
        }

        return response()->json([
            'message' => __('messages.sessions.revoked'),
        ]);
    }

    /**
     * Revoke all sessions except the current one.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()->id;

        $deleted = PersonalAccessToken::where('tokenable_type', get_class($user))
            ->where('tokenable_id', $user->id)
            ->where('id', '!=', $currentTokenId)
            ->delete();

        return response()->json([
            'message' => __('messages.sessions.all_revoked', ['count' => $deleted]),
            'revoked_count' => $deleted,
        ]);
    }

    /**
     * Parse user agent string to extract device/browser info.
     */
    private function parseUserAgent(?string $userAgent): array
    {
        if ($userAgent === null) {
            return [
                'browser' => 'Unknown',
                'platform' => 'Unknown',
                'device' => 'Unknown',
            ];
        }

        $browser = 'Unknown';
        $platform = 'Unknown';
        $device = 'Desktop';

        // Detect platform
        if (preg_match('/Windows/i', $userAgent)) {
            $platform = 'Windows';
        } elseif (preg_match('/Macintosh|Mac OS/i', $userAgent)) {
            $platform = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $platform = 'Linux';
        } elseif (preg_match('/iPhone/i', $userAgent)) {
            $platform = 'iOS';
            $device = 'iPhone';
        } elseif (preg_match('/iPad/i', $userAgent)) {
            $platform = 'iOS';
            $device = 'iPad';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $platform = 'Android';
            $device = 'Mobile';
        }

        // Detect browser
        if (preg_match('/Firefox\/[\d.]+/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Edg\/[\d.]+/i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/Chrome\/[\d.]+/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari\/[\d.]+/i', $userAgent) && ! preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
            $browser = 'Opera';
        }

        return [
            'browser' => $browser,
            'platform' => $platform,
            'device' => $device,
        ];
    }
}
