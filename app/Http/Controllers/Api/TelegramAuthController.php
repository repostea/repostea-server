<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelegramAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for Telegram Login Widget authentication endpoints.
 */
final class TelegramAuthController extends Controller
{
    public function __construct(
        private readonly TelegramAuthService $telegramService,
    ) {}

    /**
     * Handle the Telegram Login Widget callback.
     * Validates the hash and creates/logs in the user.
     */
    public function callback(Request $request): JsonResponse
    {
        // Check if Telegram login is enabled
        if (config('telegram.login_enabled') !== true) {
            return response()->json([
                'message' => 'Telegram login is not enabled',
            ], 403);
        }

        /**
         * @var array{id: int, first_name: string, last_name?: string, username?: string, photo_url?: string, auth_date: int, hash: string} $validated
         *
         * @phpstan-ignore-next-line - PHPStan false positive for Request::validate
         */
        $validated = $request->validate([
            'id' => 'required|integer',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255',
            'photo_url' => 'nullable|url',
            'auth_date' => 'required|integer',
            'hash' => 'required|string',
        ]);

        // Verify the authentication hash
        $telegramData = $validated;

        if (! $this->telegramService->verifyHash($telegramData)) {
            return response()->json([
                'message' => 'Invalid authentication data',
            ], 400);
        }

        // Check if auth is not expired (data is valid for 24 hours)
        $authDate = $validated['auth_date'];
        if (time() - $authDate > 86400) {
            return response()->json([
                'message' => 'Authentication data has expired',
            ], 400);
        }

        // Find or create the user
        $user = $this->telegramService->findOrCreateUser($telegramData);

        // Check if user is banned
        if ($user->isBanned()) {
            return response()->json([
                'message' => 'Your account has been banned',
            ], 403);
        }

        // Create Sanctum token
        $token = $user->createToken('telegram-auth')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'display_name' => $user->display_name,
                'avatar' => $user->avatar,
                'telegram_id' => $user->telegram_id,
                'is_telegram_user' => true,
                'email_verified_at' => $user->email_verified_at,
            ],
        ]);
    }

    /**
     * Check if Telegram login is enabled and return bot username.
     */
    public function status(): JsonResponse
    {
        $enabled = config('telegram.login_enabled') === true;
        /** @var string|null $botUsername */
        $botUsername = $enabled ? config('telegram.bot_username') : null;

        return response()->json([
            'enabled' => $enabled,
            'bot_username' => $botUsername,
        ]);
    }
}
