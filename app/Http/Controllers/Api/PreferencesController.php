<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;

final class PreferencesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        Log::info('[PREFERENCES] index() called', [
            'user_id' => $user?->id,
            'has_user' => ! is_null($user),
        ]);

        if (! $user) {
            Log::warning('[PREFERENCES] No user found, returning defaults');

            return response()->json([
                'layout' => 'card',
                'theme' => 'renegados1',
                'sort_by' => 'created_at',
                'sort_dir' => 'desc',
                'filters' => null,
                'content_languages' => null,
                'push_notifications' => null,
            ]);
        }

        $preferences = UserPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'layout' => 'card',
                'theme' => 'renegados1',
                'sort_by' => 'created_at',
                'sort_dir' => 'desc',
                'filters' => null,
                'content_languages' => null,
                'push_notifications' => null,
            ],
        );

        Log::info('[PREFERENCES] Returning preferences', [
            'user_id' => $user->id,
            'theme' => $preferences->theme,
            'layout' => $preferences->layout,
        ]);

        return response()->json([
            'layout' => $preferences->layout,
            'theme' => $preferences->theme,
            'sort_by' => $preferences->sort_by,
            'sort_dir' => $preferences->sort_dir,
            'filters' => $preferences->filters,
            'content_languages' => $preferences->content_languages,
            'push_notifications' => $preferences->push_notifications,
            'hide_nsfw' => $preferences->hide_nsfw ?? false,
            'hide_achievements' => $preferences->hide_achievements ?? false,
            'hide_comments' => $preferences->hide_comments ?? false,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'User not authenticated',
            ], 401);
        }

        $validated = $request->validate([
            'layout' => 'sometimes|string|in:card,compact,list',
            'theme' => 'sometimes|string|in:renegados1,yups,repostea,barrapunto,dark,dark-zinc,dark-neutral,dark-stone,hacker,solarized-dark,solarized-light,sepia,nord,dracula,high-contrast-dark,high-contrast-light',
            'sort_by' => 'sometimes|string',
            'sort_dir' => 'sometimes|string|in:asc,desc',
            'filters' => 'sometimes|nullable|array',
            'content_languages' => 'sometimes|nullable|array',
            'push_notifications' => 'sometimes|nullable|array',
            'hide_nsfw' => 'sometimes|boolean',
            'hide_achievements' => 'sometimes|boolean',
            'hide_comments' => 'sometimes|boolean',
        ]);

        // Remove null values to avoid overwriting existing data
        $data = array_filter($validated, fn ($value) => $value !== null);

        $preferences = UserPreference::updateOrCreate(
            ['user_id' => $user->id],
            $data,
        );

        return response()->json([
            'message' => 'Preferences saved successfully',
            'preferences' => $preferences,
        ]);
    }
}
