<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImageSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class AdminImageSettingsController extends Controller
{
    /**
     * Show image settings page or return JSON.
     */
    public function index(Request $request)
    {
        $settings = ImageSetting::all()
            ->groupBy('image_type')
            ->map(fn ($group) => $group->keyBy('size_name')->map(fn ($item) => [
                'id' => $item->id,
                'width' => $item->width,
                'updated_at' => $item->updated_at,
            ]));

        // Return JSON for API requests
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'settings' => $settings,
            ]);
        }

        // Return Blade view for web requests
        return view('admin.image-settings', ['settings' => $settings]);
    }

    /**
     * Update image settings for a specific type and size.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'width' => 'required|integer|min:50|max:4000',
        ]);

        $setting = ImageSetting::findOrFail($id);
        $oldWidth = $setting->width;

        $setting->update([
            'width' => $validated['width'],
        ]);

        // Clear cache for this image type
        Cache::forget("image_settings_{$setting->image_type}");

        return response()->json([
            'message' => 'Image setting updated successfully',
            'setting' => [
                'id' => $setting->id,
                'image_type' => $setting->image_type,
                'size_name' => $setting->size_name,
                'width' => $setting->width,
                'old_width' => $oldWidth,
            ],
        ]);
    }

    /**
     * Update multiple settings at once.
     */
    public function batchUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.id' => 'required|integer|exists:image_settings,id',
            'settings.*.width' => 'required|integer|min:50|max:4000',
        ]);

        $updated = [];
        $affectedTypes = [];

        foreach ($validated['settings'] as $settingData) {
            $setting = ImageSetting::find($settingData['id']);
            if ($setting) {
                $setting->update(['width' => $settingData['width']]);
                $updated[] = $setting;
                $affectedTypes[] = $setting->image_type;
            }
        }

        // Clear cache for affected image types
        foreach (array_unique($affectedTypes) as $type) {
            Cache::forget("image_settings_{$type}");
        }

        return response()->json([
            'message' => count($updated) . ' settings updated successfully',
            'updated' => $updated,
        ]);
    }

    /**
     * Reset settings to default values.
     */
    public function resetToDefaults(): JsonResponse
    {
        $defaults = [
            // Avatar sizes
            ['image_type' => 'avatar', 'size_name' => 'small', 'width' => 100],
            ['image_type' => 'avatar', 'size_name' => 'medium', 'width' => 400],
            ['image_type' => 'avatar', 'size_name' => 'large', 'width' => 800],

            // Thumbnail sizes
            ['image_type' => 'thumbnail', 'size_name' => 'small', 'width' => 360],
            ['image_type' => 'thumbnail', 'size_name' => 'medium', 'width' => 640],
            ['image_type' => 'thumbnail', 'size_name' => 'large', 'width' => 1280],

            // Inline sizes
            ['image_type' => 'inline', 'size_name' => 'small', 'width' => 430],
            ['image_type' => 'inline', 'size_name' => 'medium', 'width' => 860],
            ['image_type' => 'inline', 'size_name' => 'large', 'width' => 1920],
        ];

        foreach ($defaults as $default) {
            ImageSetting::where('image_type', $default['image_type'])
                ->where('size_name', $default['size_name'])
                ->update(['width' => $default['width']]);
        }

        // Clear all cache
        Cache::forget('image_settings_avatar');
        Cache::forget('image_settings_thumbnail');
        Cache::forget('image_settings_inline');

        return response()->json([
            'message' => 'Image settings reset to defaults successfully',
        ]);
    }
}
