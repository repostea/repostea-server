<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Service for handling Telegram Login Widget authentication.
 */
final class TelegramAuthService
{
    /**
     * Verify the authentication hash from Telegram Login Widget.
     *
     * @param  array<string, mixed>  $data  The data received from Telegram
     */
    public function verifyHash(array $data): bool
    {
        $botToken = config('telegram.bot_token');

        if (empty($botToken)) {
            return false;
        }

        // Extract the hash
        $checkHash = $data['hash'] ?? '';

        // Remove hash from data array
        unset($data['hash']);

        // Sort data alphabetically by keys
        ksort($data);

        // Create data check string
        $dataCheckArr = [];
        foreach ($data as $key => $value) {
            if ($value !== null && $value !== '') {
                $dataCheckArr[] = "{$key}={$value}";
            }
        }
        $dataCheckString = implode("\n", $dataCheckArr);

        // Create secret key from bot token
        $secretKey = hash('sha256', $botToken, true);

        // Calculate hash
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        return hash_equals($hash, $checkHash);
    }

    /**
     * Find or create a user from Telegram data.
     *
     * @param  array<string, mixed>  $data  The data received from Telegram
     */
    public function findOrCreateUser(array $data): User
    {
        $telegramId = (int) $data['id'];

        // Try to find existing user by Telegram ID
        $user = User::where('telegram_id', $telegramId)->first();

        if ($user !== null) {
            // Update user info if changed
            $this->updateUserFromTelegram($user, $data);

            return $user;
        }

        // Create new user
        return $this->createUserFromTelegram($data);
    }

    /**
     * Create a new user from Telegram data.
     *
     * @param  array<string, mixed>  $data  The data received from Telegram
     */
    private function createUserFromTelegram(array $data): User
    {
        $telegramId = (int) $data['id'];
        $firstName = (string) ($data['first_name'] ?? '');
        $lastName = (string) ($data['last_name'] ?? '');
        $telegramUsername = (string) ($data['username'] ?? '');
        $photoUrl = (string) ($data['photo_url'] ?? '');

        // Generate a unique username
        $baseUsername = $this->generateUsername($firstName, $lastName, $telegramUsername);
        $username = $this->ensureUniqueUsername($baseUsername);

        // Generate display name
        $trimmedName = trim("{$firstName} {$lastName}");
        $displayName = $trimmedName !== '' ? $trimmedName : ($telegramUsername !== '' ? $telegramUsername : "Telegram User {$telegramId}");

        $user = new User();
        $user->username = $username;
        $user->display_name = $displayName;
        $user->email = "telegram_{$telegramId}@noemail.local"; // Placeholder for Telegram users
        $user->password = bcrypt(Str::random(64)); // Random password, Telegram users login via OAuth
        $user->telegram_id = $telegramId;
        $user->telegram_username = $telegramUsername !== '' ? $telegramUsername : null;
        $user->telegram_photo_url = $photoUrl !== '' ? $photoUrl : null;
        $user->status = 'approved';
        $user->email_verified_at = now(); // Auto-verify since Telegram provides verification
        $user->save();

        return $user;
    }

    /**
     * Update existing user with latest Telegram data.
     *
     * @param  array<string, mixed>  $data  The data received from Telegram
     */
    private function updateUserFromTelegram(User $user, array $data): void
    {
        $changed = false;

        // Update photo URL if changed
        $photoUrl = (string) ($data['photo_url'] ?? '');
        if (! empty($photoUrl) && $user->telegram_photo_url !== $photoUrl) {
            $user->telegram_photo_url = $photoUrl;
            $changed = true;
        }

        // Update Telegram username if changed
        $telegramUsername = (string) ($data['username'] ?? '');
        if (! empty($telegramUsername) && $user->telegram_username !== $telegramUsername) {
            $user->telegram_username = $telegramUsername;
            $changed = true;
        }

        if ($changed) {
            $user->save();
        }
    }

    /**
     * Generate a username from Telegram user data.
     */
    private function generateUsername(string $firstName, string $lastName, string $telegramUsername): string
    {
        // Prefer Telegram username if available
        if (! empty($telegramUsername)) {
            return Str::slug($telegramUsername, '_');
        }

        // Otherwise use first name + last name
        $name = trim("{$firstName}{$lastName}");
        if (! empty($name)) {
            return Str::slug($name, '_');
        }

        // Fallback to random
        return 'telegram_' . Str::random(8);
    }

    /**
     * Ensure the username is unique by appending numbers if necessary.
     */
    private function ensureUniqueUsername(string $username): string
    {
        $originalUsername = $username;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = "{$originalUsername}_{$counter}";
            $counter++;
        }

        return $username;
    }
}
