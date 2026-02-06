<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BlueskyOAuthService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Controller for Bluesky OAuth web routes (with session).
 * These routes use the web middleware stack to support Socialite's
 * DPoP/PKCE state persistence via PHP sessions.
 */
final class BlueskyWebAuthController extends Controller
{
    public function __construct(
        private readonly BlueskyOAuthService $blueskyService,
    ) {}

    /**
     * Redirect the user to Bluesky for authorization.
     * Optionally accepts a handle query parameter as login_hint.
     */
    public function redirect(Request $request): RedirectResponse
    {
        // Check if Bluesky login is enabled
        if (config('bluesky_login.enabled') !== true) {
            return $this->redirectWithError('Bluesky login is not enabled');
        }

        if (empty(config('bluesky_login.private_key'))) {
            return $this->redirectWithError('Bluesky OAuth is not configured');
        }

        try {
            /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
            $driver = Socialite::driver('bluesky');

            // Pass handle as login_hint if provided (optional)
            $handle = $request->query('handle');
            if (is_string($handle) && ! empty(trim($handle))) {
                $driver = $driver->with(['login_hint' => trim($handle)]);
            }

            // Override scopes to match client-metadata.json declaration
            $scope = config('bluesky.oauth.metadata.scope', 'atproto');
            $driver->setScopes(explode(' ', $scope));

            return $driver->redirect();
        } catch (Exception $e) {
            Log::error('Bluesky OAuth redirect failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->redirectWithError('Failed to start Bluesky login');
        }
    }

    /**
     * Handle the callback from Bluesky after authorization.
     * Creates/finds user, generates exchange code, redirects to frontend.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            $errorDescription = $request->query('error_description', $request->query('error', 'Unknown error'));

            return $this->redirectWithError(is_string($errorDescription) ? $errorDescription : 'Unknown error');
        }

        try {
            /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
            $driver = Socialite::driver('bluesky');
            $socialiteUser = $driver->user();

            // Extract user data from Socialite
            $userData = [
                'did' => $socialiteUser->getId(),
                'handle' => $socialiteUser->getNickname() ?? $socialiteUser->getName() ?? '',
                'displayName' => $socialiteUser->getName(),
                'avatar' => $socialiteUser->getAvatar(),
                'createdAt' => $socialiteUser->user['createdAt'] ?? null,
            ];

            if (empty($userData['did']) || empty($userData['handle'])) {
                return $this->redirectWithError('Could not retrieve Bluesky account information');
            }

            // Check minimum account age if configured
            $minAgeDays = (int) config('bluesky_login.min_account_age_days', 0);
            if ($minAgeDays > 0 && ! empty($userData['createdAt'])) {
                try {
                    $createdAt = \Carbon\Carbon::parse($userData['createdAt']);
                    $accountAgeDays = $createdAt->diffInDays(now());

                    if ($accountAgeDays < $minAgeDays) {
                        return $this->redirectWithError(
                            "Your Bluesky account must be at least {$minAgeDays} days old",
                        );
                    }
                } catch (Exception) {
                    // Ignore date parsing errors
                }
            }

            // Find or create the user
            $user = $this->blueskyService->findOrCreateUser($userData);

            // Check if user is banned
            if ($user->isBanned()) {
                return $this->redirectWithError('Your account has been banned');
            }

            // Check if user is approved (for manual approval mode)
            if (config('bluesky_login.auto_approve') !== true && ! $user->isApproved()) {
                return $this->redirectWithError('Your account is pending approval', 'pending');
            }

            // Generate a one-time exchange code
            $exchangeCode = Str::random(64);
            Cache::put("bluesky_exchange:{$exchangeCode}", [
                'user_id' => $user->id,
            ], 60); // 60 seconds TTL

            // Redirect to frontend callback page with exchange code
            $clientUrl = config('app.client_url', config('app.url'));

            return redirect("{$clientUrl}/auth/bluesky/callback?exchange_code={$exchangeCode}");
        } catch (Exception $e) {
            Log::error('Bluesky OAuth callback failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->redirectWithError('Failed to complete Bluesky login');
        }
    }

    /**
     * Redirect to the frontend with an error message.
     */
    private function redirectWithError(string $message, string $status = 'error'): RedirectResponse
    {
        $clientUrl = config('app.client_url', config('app.url'));
        $params = http_build_query([
            'error' => $message,
            'status' => $status,
        ]);

        return redirect("{$clientUrl}/auth/bluesky/callback?{$params}");
    }
}
