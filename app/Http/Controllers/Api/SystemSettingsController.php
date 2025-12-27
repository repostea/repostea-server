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
        return response()->json([
            'registration_mode' => SystemSetting::get('registration_mode', 'invite_only'),
            'guest_access' => SystemSetting::get('guest_access', 'enabled'),
            'email_verification' => SystemSetting::get('email_verification', 'optional'),
        ]);
    }
}
