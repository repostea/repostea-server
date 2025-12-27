<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\UserPreference;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Trait for notification preferences functionality.
 *
 * @property-read UserPreference|null $preferences
 */
trait HasNotificationPreferences
{
    /**
     * Get the user preferences.
     */
    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    /**
     * Check if user is currently snoozed (temporary silence).
     */
    public function isSnoozed(): bool
    {
        $prefs = $this->preferences;
        if (! $prefs || $prefs->snoozed_until === null) {
            return false;
        }

        return $prefs->snoozed_until->isFuture();
    }

    /**
     * Check if current time is within user's quiet hours.
     */
    public function isWithinQuietHours(): bool
    {
        $prefs = $this->preferences;
        if (! $prefs || ! $prefs->quiet_hours_enabled) {
            return false;
        }

        if ($prefs->quiet_hours_start === null || $prefs->quiet_hours_end === null) {
            return false;
        }

        $timezone = $prefs->timezone ?? 'Europe/Madrid';
        $now = now($timezone);
        $start = $now->copy()->setTimeFromTimeString($prefs->quiet_hours_start);
        $end = $now->copy()->setTimeFromTimeString($prefs->quiet_hours_end);

        // Handle overnight quiet hours (e.g., 23:00 to 08:00)
        if ($start->gt($end)) {
            return $now->gte($start) || $now->lte($end);
        }

        return $now->between($start, $end);
    }

    /**
     * Check if user should receive instant push for a specific category.
     */
    public function shouldReceiveInstantPush(string $category): bool
    {
        // First check snooze and quiet hours
        if ($this->isSnoozed() || $this->isWithinQuietHours()) {
            return false;
        }

        $prefs = $this->preferences;
        if (! $prefs) {
            return false;
        }

        // Check if push is enabled globally
        $notificationPrefs = $prefs->notification_preferences ?? [];
        $pushEnabled = $notificationPrefs['push']['enabled'] ?? false;

        if (! $pushEnabled) {
            return false;
        }

        // Check specific category
        $instantPrefs = $notificationPrefs['push']['instant'] ?? [];

        return $instantPrefs[$category] ?? false;
    }

    /**
     * Get user's notification preferences with defaults.
     */
    public function getNotificationPreferences(): array
    {
        $prefs = $this->preferences;
        $defaults = [
            'push' => [
                'enabled' => false,
                'instant' => [
                    'comment_replies' => true,
                    'post_comments' => true,
                    'mentions' => true,
                    'agora_messages' => true,
                    'agora_replies' => true,
                    'agora_mentions' => true,
                    'achievements' => false,
                    'karma_events' => false,
                    'system' => true,
                ],
            ],
            'digest' => [
                'enabled' => false,
                'include_popular_posts' => true,
                'include_activity_summary' => true,
                'include_subscribed_subs' => true,
            ],
        ];

        if (! $prefs) {
            return $defaults;
        }

        return array_replace_recursive($defaults, $prefs->notification_preferences ?? []);
    }
}
