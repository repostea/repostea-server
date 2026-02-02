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
 * Service for handling Mbin/Kbin OAuth authentication.
 * Uses the Mbin API which differs from Mastodon's API.
 */
final class MbinOAuthService
{
    private const SCOPES = 'read user:profile';

    private const APP_NAME = 'Repostea';

    private ?int $lastErrorCode = null;

    public function __construct(
        private readonly UrlValidationService $urlValidator,
    ) {}

    /**
     * Get the last error code from a failed operation.
     */
    public function getLastErrorCode(): ?int
    {
        return $this->lastErrorCode;
    }

    /**
     * Get or create an OAuth app for an Mbin instance.
     * Reuses the MastodonApp model to store OAuth credentials.
     *
     * @throws InvalidArgumentException if instance fails SSRF validation
     */
    public function getOrCreateApp(string $instance): ?MastodonApp
    {
        $instance = $this->normalizeInstance($instance);
        $this->lastErrorCode = null;

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
     * Register a new OAuth client with an Mbin instance.
     * Mbin uses POST /api/client instead of /api/v1/apps.
     */
    private function registerApp(string $instance): ?MastodonApp
    {
        try {
            $response = Http::timeout(10)->post("https://{$instance}/api/client", [
                'name' => self::APP_NAME,
                'contactEmail' => config('mail.from.address', config('app.contact_email', 'noreply@example.com')),
                'description' => 'Login to Repostea with your Mbin/Kbin account',
                'public' => false,
                'redirectUris' => [$this->getRedirectUri()],
                'grants' => ['authorization_code', 'refresh_token'],
                'scopes' => explode(' ', self::SCOPES),
            ]);

            if (! $response->successful()) {
                $this->lastErrorCode = $response->status();
                Log::error('Failed to register Mbin app', [
                    'instance' => $instance,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            return MastodonApp::create([
                'instance' => $instance,
                'client_id' => $data['identifier'],
                'client_secret' => $data['secret'],
            ]);
        } catch (Exception $e) {
            $this->lastErrorCode = 0;
            Log::error('Exception while registering Mbin app', [
                'instance' => $instance,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get the authorization URL for an Mbin instance.
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

        return "https://{$instance}/authorize?{$params}";
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
            $response = Http::timeout(10)->post("https://{$instance}/token", [
                'client_id' => $app->client_id,
                'client_secret' => $app->client_secret,
                'redirect_uri' => $this->getRedirectUri(),
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);

            if (! $response->successful()) {
                Log::error('Failed to get Mbin access token', [
                    'instance' => $instance,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json('access_token');
        } catch (Exception $e) {
            Log::error('Exception while getting Mbin access token', [
                'instance' => $instance,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get user account info from Mbin instance.
     * Mbin uses /api/users/me or /api/user endpoint.
     *
     * @throws InvalidArgumentException if instance fails SSRF validation
     */
    public function getAccountInfo(string $instance, string $accessToken): ?array
    {
        $instance = $this->normalizeInstance($instance);

        // Validate instance to prevent SSRF attacks
        $this->urlValidator->validateInstance($instance);

        try {
            // Try /api/users/me first (newer Mbin versions)
            $response = Http::timeout(10)
                ->withToken($accessToken)
                ->get("https://{$instance}/api/users/me");

            if (! $response->successful()) {
                // Fallback to /api/user (older versions)
                $response = Http::timeout(10)
                    ->withToken($accessToken)
                    ->get("https://{$instance}/api/user");
            }

            if (! $response->successful()) {
                Log::error('Failed to get Mbin account info', [
                    'instance' => $instance,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Exception while getting Mbin account info', [
                'instance' => $instance,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Find or create a user from Mbin account info.
     */
    public function findOrCreateUser(string $instance, array $accountInfo): User
    {
        $instance = $this->normalizeInstance($instance);

        // Mbin uses 'userId' or 'id' for the user ID
        $userId = $accountInfo['userId'] ?? $accountInfo['id'] ?? null;

        if ($userId === null) {
            throw new Exception('Could not determine user ID from Mbin account info');
        }

        // Build the unique federated ID
        $federatedId = $userId . '@' . $instance;

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
     * Create a new federated user from Mbin account.
     */
    private function createFederatedUser(string $instance, array $accountInfo, string $federatedId): User
    {
        // Mbin uses 'username' for the username
        $mbinUsername = $accountInfo['username'] ?? $accountInfo['name'] ?? 'user';

        // Generate unique username with instance suffix (e.g., user@fedia.io)
        $baseUsername = $mbinUsername . '@' . $instance;
        $username = $baseUsername;
        $suffix = 1;

        // In the rare case of collision, add numeric suffix
        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $suffix;
            $suffix++;
        }

        // Parse account creation date
        $createdAt = null;
        if (isset($accountInfo['createdAt'])) {
            try {
                $createdAt = \Carbon\Carbon::parse($accountInfo['createdAt']);
            } catch (Exception $e) {
                // Ignore parsing errors
            }
        }

        // Get avatar - Mbin may use 'avatar' or 'avatar.filePath'
        $avatar = null;
        if (isset($accountInfo['avatar'])) {
            if (is_array($accountInfo['avatar'])) {
                $avatar = $accountInfo['avatar']['filePath'] ?? $accountInfo['avatar']['storageUrl'] ?? null;
            } else {
                $avatar = $accountInfo['avatar'];
            }
        }

        $user = User::create([
            'username' => $username,
            'email' => null, // Federated users don't have email
            'password' => bcrypt(Str::random(64)), // Random password, federated users login via OAuth
            'status' => 'approved', // Auto-approve federated users
            'display_name' => $accountInfo['username'] ?? $mbinUsername,
            'bio' => $accountInfo['about'] ?? null,
            'avatar_url' => $avatar,
            'federated_id' => $federatedId,
            'federated_instance' => $instance,
            'federated_username' => $mbinUsername,
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
        $avatar = null;
        if (isset($accountInfo['avatar'])) {
            if (is_array($accountInfo['avatar'])) {
                $avatar = $accountInfo['avatar']['filePath'] ?? $accountInfo['avatar']['storageUrl'] ?? null;
            } else {
                $avatar = $accountInfo['avatar'];
            }
        }

        if ($avatar && $user->avatar_url !== $avatar) {
            $user->avatar_url = $avatar;
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
        return config('app.client_url', config('app.url')) . '/auth/mbin/callback';
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
     * Validate that an instance is a valid Mbin/Kbin server.
     * Checks for Mbin-specific API endpoints.
     */
    public function validateMbinInstance(string $instance): bool
    {
        $instance = $this->normalizeInstance($instance);

        // First validate against SSRF
        try {
            $this->urlValidator->validateInstance($instance);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        try {
            // Check for Mbin API - they have /api/info endpoint
            $response = Http::timeout(5)->get("https://{$instance}/api/info");

            if ($response->successful()) {
                return true;
            }

            // Fallback: check nodeinfo for mbin/kbin software
            $response = Http::timeout(5)->get("https://{$instance}/.well-known/nodeinfo");

            if ($response->successful()) {
                $links = $response->json('links', []);
                foreach ($links as $link) {
                    if (isset($link['href'])) {
                        // Validate nodeinfo URL before fetching
                        try {
                            $this->urlValidator->validate($link['href']);
                        } catch (InvalidArgumentException $e) {
                            continue;
                        }

                        $nodeInfo = Http::timeout(5)->get($link['href']);
                        if ($nodeInfo->successful()) {
                            $software = strtolower($nodeInfo->json('software.name', ''));
                            if (str_contains($software, 'mbin') || str_contains($software, 'kbin')) {
                                return true;
                            }
                        }
                    }
                }
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }
}
