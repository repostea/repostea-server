<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MastodonApp;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service for handling Mastodon OAuth authentication.
 * Supports dynamic app registration and user authentication.
 */
final class MastodonOAuthService
{
    private const SCOPES = 'read:accounts';

    private const APP_NAME = 'Repostea';

    public function __construct(
        private readonly UrlValidationService $urlValidator,
    ) {}

    /**
     * Get or create an OAuth app for a Mastodon instance.
     *
     * @throws InvalidArgumentException if instance fails SSRF validation
     */
    public function getOrCreateApp(string $instance): ?MastodonApp
    {
        $instance = $this->normalizeInstance($instance);

        // Validate instance to prevent SSRF attacks
        $this->urlValidator->validateInstance($instance);

        // Check if we already have an app for this instance
        $app = MastodonApp::where('instance', $instance)->first();

        if ($app !== null) {
            return $app;
        }

        // Register a new app with the instance
        return $this->registerApp($instance);
    }

    /**
     * Register a new OAuth app with a Mastodon instance.
     */
    private function registerApp(string $instance): ?MastodonApp
    {
        try {
            $response = Http::timeout(10)->post("https://{$instance}/api/v1/apps", [
                'client_name' => self::APP_NAME,
                'redirect_uris' => $this->getRedirectUri(),
                'scopes' => self::SCOPES,
                'website' => config('app.url'),
            ]);

            if (! $response->successful()) {
                Log::error('Failed to register Mastodon app', [
                    'instance' => $instance,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            return MastodonApp::create([
                'instance' => $instance,
                'client_id' => $data['client_id'],
                'client_secret' => $data['client_secret'],
            ]);
        } catch (Exception $e) {
            Log::error('Exception while registering Mastodon app', [
                'instance' => $instance,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get the authorization URL for a Mastodon instance.
     */
    public function getAuthorizationUrl(string $instance, string $state): ?string
    {
        $app = $this->getOrCreateApp($instance);

        if ($app === null) {
            return null;
        }

        $params = http_build_query([
            'client_id' => $app->client_id,
            'redirect_uri' => $this->getRedirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'state' => $state,
        ]);

        return "https://{$instance}/oauth/authorize?{$params}";
    }

    /**
     * Exchange authorization code for access token.
     *
     * @throws InvalidArgumentException if instance fails SSRF validation
     */
    public function getAccessToken(string $instance, string $code): ?string
    {
        $instance = $this->normalizeInstance($instance);

        // Validate instance to prevent SSRF attacks
        $this->urlValidator->validateInstance($instance);

        $app = MastodonApp::where('instance', $instance)->first();

        if ($app === null) {
            return null;
        }

        try {
            $response = Http::timeout(10)->post("https://{$instance}/oauth/token", [
                'client_id' => $app->client_id,
                'client_secret' => $app->client_secret,
                'redirect_uri' => $this->getRedirectUri(),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'scope' => self::SCOPES,
            ]);

            if (! $response->successful()) {
                Log::error('Failed to get access token', [
                    'instance' => $instance,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json('access_token');
        } catch (Exception $e) {
            Log::error('Exception while getting access token', [
                'instance' => $instance,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get user account info from Mastodon instance.
     *
     * @throws InvalidArgumentException if instance fails SSRF validation
     */
    public function getAccountInfo(string $instance, string $accessToken): ?array
    {
        $instance = $this->normalizeInstance($instance);

        // Validate instance to prevent SSRF attacks
        $this->urlValidator->validateInstance($instance);

        try {
            $response = Http::timeout(10)
                ->withToken($accessToken)
                ->get("https://{$instance}/api/v1/accounts/verify_credentials");

            if (! $response->successful()) {
                Log::error('Failed to get account info', [
                    'instance' => $instance,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Exception while getting account info', [
                'instance' => $instance,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Find or create a user from Mastodon account info.
     */
    public function findOrCreateUser(string $instance, array $accountInfo): User
    {
        $instance = $this->normalizeInstance($instance);

        // Build the unique federated ID
        $federatedId = $accountInfo['id'] . '@' . $instance;

        // Check if user already exists (including soft-deleted)
        $user = User::withTrashed()->where('federated_id', $federatedId)->first();

        if ($user !== null) {
            // Restore if soft-deleted
            if ($user->trashed()) {
                $user->restore();
            }

            // Update avatar if changed
            $this->updateUserAvatar($user, $accountInfo);

            return $user;
        }

        // Create new user
        return $this->createFederatedUser($instance, $accountInfo, $federatedId);
    }

    /**
     * Create a new federated user.
     */
    private function createFederatedUser(string $instance, array $accountInfo, string $federatedId): User
    {
        // Generate unique username with instance suffix (e.g., clonner@mastodon.social)
        $baseUsername = $accountInfo['username'] . '@' . $instance;
        $username = $baseUsername;
        $suffix = 1;

        // In the rare case of collision, add numeric suffix
        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $suffix;
            $suffix++;
        }

        // Parse account creation date
        $createdAt = null;
        if (isset($accountInfo['created_at'])) {
            try {
                $createdAt = \Carbon\Carbon::parse($accountInfo['created_at']);
            } catch (Exception $e) {
                // Ignore parsing errors
            }
        }

        $user = User::create([
            'username' => $username,
            'email' => null, // Federated users don't have email
            'password' => bcrypt(Str::random(64)), // Random password, federated users login via OAuth
            'status' => 'approved', // Auto-approve federated users
            'display_name' => $accountInfo['display_name'] !== '' ? $accountInfo['display_name'] : $accountInfo['username'],
            'bio' => $accountInfo['note'] ?? null,
            'avatar_url' => $accountInfo['avatar'] ?? null,
            'federated_id' => $federatedId,
            'federated_instance' => $instance,
            'federated_username' => $accountInfo['username'],
            'federated_account_created_at' => $createdAt,
            'email_verified_at' => now(), // Consider federated users as verified
        ]);

        return $user;
    }

    /**
     * Update user avatar if it changed.
     */
    private function updateUserAvatar(User $user, array $accountInfo): void
    {
        $newAvatar = $accountInfo['avatar'] ?? null;

        if ($newAvatar && $user->avatar_url !== $newAvatar) {
            $user->avatar_url = $newAvatar;
            $user->save();
        }
    }

    /**
     * Normalize instance URL (remove protocol, trailing slashes).
     */
    private function normalizeInstance(string $instance): string
    {
        $instance = preg_replace('#^https?://#', '', $instance);
        $instance = rtrim($instance, '/');

        return strtolower($instance);
    }

    /**
     * Get the OAuth callback redirect URI.
     */
    private function getRedirectUri(): string
    {
        return config('app.frontend_url', config('app.url')) . '/auth/mastodon/callback';
    }

    /**
     * Check if an instance is blocked.
     */
    public function isInstanceBlocked(string $instance): bool
    {
        $instance = $this->normalizeInstance($instance);
        $blockedInstances = config('fediverse_login.blocked_instances', []);

        return in_array($instance, $blockedInstances, true);
    }

    /**
     * Validate that an instance is a valid Mastodon server.
     */
    public function validateMastodonInstance(string $instance): bool
    {
        $instance = $this->normalizeInstance($instance);

        // First validate against SSRF
        try {
            $this->urlValidator->validateInstance($instance);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        try {
            $response = Http::timeout(5)->get("https://{$instance}/api/v1/instance");

            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }
}
