<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for notification preferences and snooze management.
 * Push subscriptions are in PushSubscriptionController.
 * Core notification CRUD is in NotificationController.
 */
final class NotificationPreferencesController extends Controller
{
    /**
     * Get user's notification preferences.
     */
    public function getNotificationPreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        $prefs = $user->preferences ?? new UserPreference();

        return response()->json([
            'notification_preferences' => $user->getNotificationPreferences(),
            'digest_frequency' => $prefs->digest_frequency ?? 'none',
            'digest_day' => $prefs->digest_day,
            'digest_hour' => $prefs->digest_hour ?? 9,
            'quiet_hours_enabled' => $prefs->quiet_hours_enabled ?? false,
            'quiet_hours_start' => $prefs->quiet_hours_start,
            'quiet_hours_end' => $prefs->quiet_hours_end,
            'timezone' => $prefs->timezone ?? 'Europe/Madrid',
            'snoozed_until' => $prefs->snoozed_until?->toISOString(),
            'is_snoozed' => $prefs->isSnoozed(),
            'snooze_remaining_minutes' => $prefs->getSnoozeRemainingMinutes(),
        ]);
    }

    /**
     * Update user's notification preferences.
     */
    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $request->validate([
            'notification_preferences' => 'nullable|array',
            'notification_preferences.push.enabled' => 'nullable|boolean',
            'notification_preferences.push.instant' => 'nullable|array',
            'notification_preferences.digest.enabled' => 'nullable|boolean',
            'digest_frequency' => 'nullable|in:none,daily,weekly',
            'digest_day' => 'nullable|integer|min:0|max:6',
            'digest_hour' => 'nullable|integer|min:0|max:23',
            'quiet_hours_enabled' => 'nullable|boolean',
            'quiet_hours_start' => 'nullable|date_format:H:i',
            'quiet_hours_end' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        $prefs = $user->preferences ?? UserPreference::create(['user_id' => $user->id]);

        $updateData = [];

        if ($request->has('notification_preferences')) {
            $current = $prefs->notification_preferences ?? [];
            $updateData['notification_preferences'] = array_replace_recursive(
                $current,
                $request->input('notification_preferences'),
            );
        }

        if ($request->has('digest_frequency')) {
            $updateData['digest_frequency'] = $request->input('digest_frequency');
        }

        if ($request->has('digest_day')) {
            $updateData['digest_day'] = $request->input('digest_day');
        }

        if ($request->has('digest_hour')) {
            $updateData['digest_hour'] = $request->input('digest_hour');
        }

        if ($request->has('quiet_hours_enabled')) {
            $updateData['quiet_hours_enabled'] = $request->input('quiet_hours_enabled');
        }

        if ($request->has('quiet_hours_start')) {
            $updateData['quiet_hours_start'] = $request->input('quiet_hours_start');
        }

        if ($request->has('quiet_hours_end')) {
            $updateData['quiet_hours_end'] = $request->input('quiet_hours_end');
        }

        if ($request->has('timezone')) {
            $updateData['timezone'] = $request->input('timezone');
        }

        $prefs->update($updateData);

        return response()->json([
            'message' => __('notifications.preferences_updated'),
            'preferences' => $this->getNotificationPreferences($request)->getData(),
        ]);
    }

    /**
     * Snooze notifications for a specified duration.
     */
    public function snooze(Request $request): JsonResponse
    {
        $request->validate([
            'hours' => 'required_without:until_tomorrow|integer|min:1|max:168', // Max 1 week
            'until_tomorrow' => 'required_without:hours|boolean',
        ]);

        $user = $request->user();
        $prefs = $user->preferences ?? UserPreference::create(['user_id' => $user->id]);

        if ($request->input('until_tomorrow')) {
            $prefs->snoozeUntilTomorrow();
        } else {
            $prefs->snooze($request->input('hours'));
        }

        return response()->json([
            'message' => __('notifications.snooze_activated'),
            'snoozed_until' => $prefs->snoozed_until->toISOString(),
            'snooze_remaining_minutes' => $prefs->getSnoozeRemainingMinutes(),
        ]);
    }

    /**
     * Cancel active snooze.
     */
    public function unsnooze(Request $request): JsonResponse
    {
        $user = $request->user();
        $prefs = $user->preferences;

        if ($prefs) {
            $prefs->unsnooze();
        }

        return response()->json([
            'message' => __('notifications.snooze_cancelled'),
        ]);
    }
}
