<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

final class SystemSettingsController extends Controller
{
    /**
     * Get public system settings (no auth required).
     */
    public function index(): JsonResponse
    {
        $fediverseLoginEnabled = config('fediverse_login.enabled', false) === true;
        $telegramEnabled = config('telegram.login_enabled') === true;
        $blueskyEnabled = config('bluesky_login.enabled', false) === true
            && ! empty(config('bluesky_login.private_key'));

        /** @var string|null $botUsername */
        $botUsername = $telegramEnabled ? config('telegram.bot_username') : null;

        return response()->json([
            'registration_mode' => SystemSetting::get('registration_mode', 'invite_only'),
            'guest_access' => SystemSetting::get('guest_access', 'enabled'),
            'email_verification' => SystemSetting::get('email_verification', 'optional'),
            'social_providers' => [
                'mastodon' => ['enabled' => $fediverseLoginEnabled],
                'mbin' => ['enabled' => $fediverseLoginEnabled],
                'telegram' => [
                    'enabled' => $telegramEnabled,
                    'bot_username' => $botUsername,
                ],
                'bluesky' => ['enabled' => $blueskyEnabled],
            ],
        ]);
    }
}
