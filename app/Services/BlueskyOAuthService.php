<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Str;

/**
 * Service for handling Bluesky/AT Protocol OAuth user creation and lookup.
 */
final class BlueskyOAuthService
{
    /**
     * Find or create a user from Bluesky Socialite user data.
     *
     * @param  array{did: string, handle: string, displayName: string|null, avatar: string|null, createdAt: string|null}  $userData
     */
    public function findOrCreateUser(array $userData): User
    {
        // DID is immutable, unlike the handle
        $federatedId = $userData['did'] . '@bsky';

        // Check if user already exists (including soft-deleted)
        $user = User::withTrashed()->where('federated_id', $federatedId)->first();

        if ($user !== null) {
            // Restore if soft-deleted
            if ($user->trashed()) {
                $user->restore();
            }

            // Update avatar and handle if changed
            $this->updateUser($user, $userData);

            return $user;
        }

        // Create new user
        return $this->createBlueskyUser($userData, $federatedId);
    }

    /**
     * Extract the instance from a Bluesky handle.
     * e.g. "alice.bsky.social" → "bsky.social"
     * e.g. "bob.custom-domain.com" → "custom-domain.com".
     */
    public function extractInstance(string $handle): string
    {
        $parts = explode('.', $handle);

        if (count($parts) <= 2) {
            return $handle;
        }

        // Remove the first segment (username part)
        return implode('.', array_slice($parts, 1));
    }

    /**
     * Create a new user from Bluesky account data.
     *
     * @param  array{did: string, handle: string, displayName: string|null, avatar: string|null, createdAt: string|null}  $userData
     */
    private function createBlueskyUser(array $userData, string $federatedId): User
    {
        // Generate unique username with bluesky suffix
        $baseUsername = $userData['handle'] . '@bluesky';
        $username = $baseUsername;
        $suffix = 1;

        // In the rare case of collision, add numeric suffix
        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $suffix;
            $suffix++;
        }

        // Parse account creation date
        $createdAt = null;
        if (! empty($userData['createdAt'])) {
            try {
                $createdAt = \Carbon\Carbon::parse($userData['createdAt']);
            } catch (Exception) {
                // Ignore parsing errors
            }
        }

        $user = User::create([
            'username' => $username,
            'email' => null,
            'password' => bcrypt(Str::random(64)),
            'status' => 'approved',
            'display_name' => $userData['displayName'] ?? $userData['handle'],
            'bio' => null,
            'avatar_url' => $userData['avatar'] ?? null,
            'federated_id' => $federatedId,
            'federated_instance' => $this->extractInstance($userData['handle']),
            'federated_username' => $userData['handle'],
            'federated_account_created_at' => $createdAt,
            'email_verified_at' => now(),
        ]);

        return $user;
    }

    /**
     * Update user data if it changed (avatar, handle, display name).
     *
     * @param  array{did: string, handle: string, displayName: string|null, avatar: string|null, createdAt: string|null}  $userData
     */
    private function updateUser(User $user, array $userData): void
    {
        $changed = false;

        // Update avatar if changed
        $newAvatar = $userData['avatar'] ?? null;
        if ($newAvatar && $user->avatar_url !== $newAvatar) {
            $user->avatar_url = $newAvatar;
            $changed = true;
        }

        // Update federated username if handle changed
        if ($user->federated_username !== $userData['handle']) {
            $user->federated_username = $userData['handle'];
            $user->federated_instance = $this->extractInstance($userData['handle']);
            $changed = true;
        }

        // Update display name if changed
        $displayName = $userData['displayName'] ?? $userData['handle'];
        if ($user->display_name !== $displayName) {
            $user->display_name = $displayName;
            $changed = true;
        }

        if ($changed) {
            $user->save();
        }
    }
}
