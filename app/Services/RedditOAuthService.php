<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for handling Reddit OAuth authentication.
 */
final class RedditOAuthService
{
    private const SCOPES = 'identity';

    private const AUTH_URL = 'https://www.reddit.com/api/v1/authorize';

    private const TOKEN_URL = 'https://www.reddit.com/api/v1/access_token';

    private const API_URL = 'https://oauth.reddit.com';

    /**
     * Get the authorization URL for Reddit OAuth.
     */
    public function getAuthorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => config('reddit_login.client_id'),
            'response_type' => 'code',
            'state' => $state,
            'redirect_uri' => $this->getRedirectUri(),
            'duration' => 'temporary',
            'scope' => self::SCOPES,
        ]);

        return self::AUTH_URL . '?' . $params;
    }

    /**
     * Exchange authorization code for access token.
     */
    public function getAccessToken(string $code): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withBasicAuth(
                    config('reddit_login.client_id'),
                    config('reddit_login.client_secret'),
                )
                ->withHeaders([
                    'User-Agent' => $this->getUserAgent(),
                ])
                ->asForm()
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->getRedirectUri(),
                ]);

            if (! $response->successful()) {
                Log::error('Failed to get Reddit access token', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json('access_token');
        } catch (Exception $e) {
            Log::error('Exception while getting Reddit access token', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get user account info from Reddit.
     */
    public function getAccountInfo(string $accessToken): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withToken($accessToken)
                ->withHeaders([
                    'User-Agent' => $this->getUserAgent(),
                ])
                ->get(self::API_URL . '/api/v1/me');

            if (! $response->successful()) {
                Log::error('Failed to get Reddit account info', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Exception while getting Reddit account info', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Find or create a user from Reddit account info.
     */
    public function findOrCreateUser(array $accountInfo): User
    {
        // Build the unique federated ID for Reddit
        $federatedId = $accountInfo['id'] . '@reddit.com';

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
        return $this->createRedditUser($accountInfo, $federatedId);
    }

    /**
     * Create a new user from Reddit account.
     */
    private function createRedditUser(array $accountInfo, string $federatedId): User
    {
        // Generate unique username with reddit suffix
        $baseUsername = $accountInfo['name'] . '@reddit';
        $username = $baseUsername;
        $suffix = 1;

        // In the rare case of collision, add numeric suffix
        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $suffix;
            $suffix++;
        }

        // Parse account creation date (Reddit uses Unix timestamp)
        $createdAt = null;
        if (isset($accountInfo['created_utc'])) {
            try {
                $createdAt = \Carbon\Carbon::createFromTimestamp((int) $accountInfo['created_utc']);
            } catch (Exception $e) {
                // Ignore parsing errors
            }
        }

        // Get avatar URL (Reddit has different avatar formats)
        $avatarUrl = $this->extractAvatarUrl($accountInfo);

        $user = User::create([
            'username' => $username,
            'email' => null, // Reddit users don't share email via OAuth
            'password' => bcrypt(Str::random(64)),
            'status' => 'approved',
            'display_name' => $accountInfo['name'],
            'bio' => $accountInfo['subreddit']['public_description'] ?? null,
            'avatar_url' => $avatarUrl,
            'federated_id' => $federatedId,
            'federated_instance' => 'reddit.com',
            'federated_username' => $accountInfo['name'],
            'federated_account_created_at' => $createdAt,
            'email_verified_at' => now(),
        ]);

        return $user;
    }

    /**
     * Extract avatar URL from Reddit account info.
     */
    private function extractAvatarUrl(array $accountInfo): ?string
    {
        // Reddit has different avatar sources
        if (! empty($accountInfo['snoovatar_img'])) {
            return $accountInfo['snoovatar_img'];
        }

        if (! empty($accountInfo['icon_img'])) {
            // Clean up URL (remove query params that may expire)
            $url = $accountInfo['icon_img'];
            $parsed = parse_url($url);
            if ($parsed !== false && isset($parsed['scheme'], $parsed['host'], $parsed['path'])) {
                return $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'];
            }

            return $url;
        }

        return null;
    }

    /**
     * Update user avatar if it changed.
     */
    private function updateUserAvatar(User $user, array $accountInfo): void
    {
        $newAvatar = $this->extractAvatarUrl($accountInfo);

        if ($newAvatar && $user->avatar_url !== $newAvatar) {
            $user->avatar_url = $newAvatar;
            $user->save();
        }
    }

    /**
     * Get the OAuth callback redirect URI.
     */
    private function getRedirectUri(): string
    {
        return config('app.frontend_url', config('app.url')) . '/auth/reddit/callback';
    }

    /**
     * Get User-Agent string for Reddit API requests.
     */
    private function getUserAgent(): string
    {
        $appName = config('app.name', 'Repostea');
        $version = '1.0.0';

        return "web:{$appName}:{$version} (by /u/" . config('reddit_login.bot_username', 'repostea') . ')';
    }
}
