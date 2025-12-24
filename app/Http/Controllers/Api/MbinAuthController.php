<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MbinOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Controller for Mbin/Kbin OAuth authentication endpoints.
 */
final class MbinAuthController extends Controller
{
    public function __construct(
        private readonly MbinOAuthService $mbinService,
    ) {}

    /**
     * Start the OAuth flow - returns the authorization URL.
     */
    public function redirect(Request $request): JsonResponse
    {
        $request->validate([
            'instance' => 'required|string|max:255',
        ]);

        // Check if federation is enabled
        if (config('fediverse_login.enabled') !== true) {
            return response()->json([
                'message' => 'Federated login is not enabled',
            ], 403);
        }

        /** @var string $instance */
        $instance = $request->input('instance');

        // Check if instance is blocked
        if ($this->mbinService->isInstanceBlocked($instance)) {
            return response()->json([
                'message' => 'This instance is not allowed',
            ], 403);
        }

        // Validate that the instance is a valid Mbin/Kbin server
        if (! $this->mbinService->validateMbinInstance($instance)) {
            return response()->json([
                'message' => 'Invalid Mbin/Kbin instance',
            ], 400);
        }

        // Generate a random state for CSRF protection
        $state = Str::random(40);

        // Store state in cache with instance info (expires in 10 minutes)
        Cache::put("mbin_oauth:{$state}", [
            'instance' => $instance,
            'created_at' => now()->timestamp,
        ], 600);

        // Get the authorization URL
        $authUrl = $this->mbinService->getAuthorizationUrl($instance, $state);

        if ($authUrl === null) {
            $errorCode = $this->mbinService->getLastErrorCode();

            // Return specific error for 403 (instance doesn't allow external apps)
            if ($errorCode === 403) {
                return response()->json([
                    'message' => 'This instance does not allow external app registration',
                    'error_code' => 'instance_forbidden',
                ], 403);
            }

            return response()->json([
                'message' => 'Failed to connect to the instance',
                'error_code' => 'connection_failed',
            ], 500);
        }

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

        // Verify state and get instance
        /** @var array{instance: string, created_at: int}|null $stateData */
        $stateData = Cache::pull("mbin_oauth:{$state}");

        if ($stateData === null) {
            return response()->json([
                'message' => 'Invalid or expired state',
            ], 400);
        }

        $instance = $stateData['instance'];

        // Exchange code for access token
        $accessToken = $this->mbinService->getAccessToken($instance, $code);

        if ($accessToken === null) {
            return response()->json([
                'message' => 'Failed to obtain access token',
            ], 400);
        }

        // Get user account info from Mbin
        $accountInfo = $this->mbinService->getAccountInfo($instance, $accessToken);

        if ($accountInfo === null) {
            return response()->json([
                'message' => 'Failed to get account information',
            ], 400);
        }

        // Check minimum account age (if createdAt is available)
        $minAgeDays = (int) config('fediverse_login.min_account_age_days', 0);
        if ($minAgeDays > 0 && isset($accountInfo['createdAt'])) {
            $createdAt = \Carbon\Carbon::parse($accountInfo['createdAt']);
            $accountAgeDays = $createdAt->diffInDays(now());

            if ($accountAgeDays < $minAgeDays) {
                return response()->json([
                    'message' => "Your account must be at least {$minAgeDays} days old",
                ], 403);
            }
        }

        // Find or create the user
        $user = $this->mbinService->findOrCreateUser($instance, $accountInfo);

        // Check if user is approved (for manual approval mode)
        if (config('fediverse_login.auto_approve') !== true && ! $user->isApproved()) {
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
        $token = $user->createToken('mbin-auth')->plainTextToken;

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
     * Check if Mbin federation is enabled and return status.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'enabled' => config('fediverse_login.enabled', false),
        ]);
    }
}
