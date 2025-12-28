<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RedditOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Controller for Reddit OAuth authentication endpoints.
 */
final class RedditAuthController extends Controller
{
    public function __construct(
        private readonly RedditOAuthService $redditService,
    ) {}

    /**
     * Start the OAuth flow - returns the authorization URL.
     */
    public function redirect(): JsonResponse
    {
        // Check if Reddit login is enabled
        if (config('reddit_login.enabled') !== true) {
            return response()->json([
                'message' => 'Reddit login is not enabled',
            ], 403);
        }

        // Check if client credentials are configured
        if (empty(config('reddit_login.client_id')) || empty(config('reddit_login.client_secret'))) {
            return response()->json([
                'message' => 'Reddit OAuth is not configured',
            ], 500);
        }

        // Generate a random state for CSRF protection
        $state = Str::random(40);

        // Store state in cache (expires in 10 minutes)
        Cache::put("reddit_oauth:{$state}", [
            'created_at' => now()->timestamp,
        ], 600);

        // Get the authorization URL
        $authUrl = $this->redditService->getAuthorizationUrl($state);

        return response()->json([
            'url' => $authUrl,
            'state' => $state,
        ]);
    }

    /**
     * Handle the OAuth callback - exchange code for token and login/register user.
     */
    public function callback(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        /** @var string $code */
        $code = $request->input('code');
        /** @var string $state */
        $state = $request->input('state');

        // Verify state
        /** @var array{created_at: int}|null $stateData */
        $stateData = Cache::pull("reddit_oauth:{$state}");

        if ($stateData === null) {
            return response()->json([
                'message' => 'Invalid or expired state',
            ], 400);
        }

        // Exchange code for access token
        $accessToken = $this->redditService->getAccessToken($code);

        if ($accessToken === null) {
            return response()->json([
                'message' => 'Failed to obtain access token',
            ], 400);
        }

        // Get user account info from Reddit
        $accountInfo = $this->redditService->getAccountInfo($accessToken);

        if ($accountInfo === null) {
            return response()->json([
                'message' => 'Failed to get account information',
            ], 400);
        }

        // Check minimum account age if configured
        $minAgeDays = (int) config('reddit_login.min_account_age_days', 0);
        if ($minAgeDays > 0 && isset($accountInfo['created_utc'])) {
            $createdAt = \Carbon\Carbon::createFromTimestamp((int) $accountInfo['created_utc']);
            $accountAgeDays = $createdAt->diffInDays(now());

            if ($accountAgeDays < $minAgeDays) {
                return response()->json([
                    'message' => "Your Reddit account must be at least {$minAgeDays} days old",
                ], 403);
            }
        }

        // Find or create the user
        $user = $this->redditService->findOrCreateUser($accountInfo);

        // Check if user is approved (for manual approval mode)
        if (config('reddit_login.auto_approve') !== true && ! $user->isApproved()) {
            return response()->json([
                'message' => 'Your account is pending approval',
                'status' => 'pending',
            ], 403);
        }

        // Check if user is banned
        if ($user->isBanned()) {
            return response()->json([
                'message' => 'Your account has been banned',
            ], 403);
        }

        // Create Sanctum token
        $token = $user->createToken('reddit-auth')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'display_name' => $user->display_name,
                'avatar' => $user->avatar,
                'federated_handle' => $user->federated_handle,
                'is_federated' => true,
                'email_verified_at' => $user->email_verified_at,
            ],
        ]);
    }

    /**
     * Check if Reddit login is enabled and return status.
     */
    public function status(): JsonResponse
    {
        $enabled = config('reddit_login.enabled', false) === true
            && ! empty(config('reddit_login.client_id'))
            && ! empty(config('reddit_login.client_secret'));

        return response()->json([
            'enabled' => $enabled,
        ]);
    }
}
