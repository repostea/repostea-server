<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class SettingsController extends Controller
{
    /**
     * Get all system settings.
     */
    public function index(Request $request)
    {
        $settings = SystemSetting::all()->mapWithKeys(fn ($setting) => [$setting->key => [
            'value' => SystemSetting::castValue($setting->value, $setting->type),
            'type' => $setting->type,
            'description' => $setting->description,
        ]]);

        // Return JSON for API requests
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json($settings);
        }

        // Return Blade view for web requests
        return view('admin.settings', ['settings' => $settings]);
    }

    /**
     * Update system settings.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'registration_mode' => 'sometimes|in:open,invite_only,closed',
            'email_verification' => 'sometimes|in:required,optional,disabled',
            'guest_access' => 'sometimes|in:enabled,disabled',
            'registration_approval' => 'sometimes|in:none,required',
            // Federation settings
            'federation_auto_publish' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            // Return JSON for API requests
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Redirect back for web requests
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $allowedKeys = [
            'registration_mode',
            'email_verification',
            'guest_access',
            'registration_approval',
            'federation_auto_publish',
        ];

        foreach ($request->all() as $key => $value) {
            if (in_array($key, $allowedKeys, true)) {
                $type = $key === 'federation_auto_publish' ? 'boolean' : 'string';
                SystemSetting::set($key, $value, $type);
            }
        }

        SystemSetting::clearCache();

        // Return JSON for API requests
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Settings updated successfully',
            ]);
        }

        // Redirect for web requests
        return redirect()->route('admin.settings')->with('success', 'Settings updated successfully!');
    }

    /**
     * Get federation-specific settings.
     */
    public function getFederationSettings()
    {
        return response()->json([
            'federation_auto_publish' => SystemSetting::get('federation_auto_publish', true),
            'activitypub_enabled' => config('activitypub.enabled', false),
            'activitypub_domain' => config('activitypub.domain'),
            'require_signatures' => config('activitypub.signatures.require', true),
        ]);
    }

    /**
     * Update federation-specific settings.
     */
    public function updateFederationSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'federation_auto_publish' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('federation_auto_publish')) {
            SystemSetting::set(
                'federation_auto_publish',
                $request->boolean('federation_auto_publish'),
                'boolean',
                'Automatically federate new posts to the Fediverse',
            );
        }

        SystemSetting::clearCache();

        return response()->json([
            'message' => 'Federation settings updated successfully',
        ]);
    }
}
