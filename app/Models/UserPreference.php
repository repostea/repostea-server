<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $layout
 * @property string $theme
 * @property string $sort_by
 * @property string $sort_dir
 * @property array<array-key, mixed>|null $filters
 * @property bool $hide_nsfw
 * @property bool $hide_achievements
 * @property bool $hide_comments
 * @property array<array-key, mixed>|null $content_languages
 * @property array<array-key, mixed>|null $push_notifications
 * @property array<array-key, mixed>|null $notification_preferences
 * @property string $digest_frequency
 * @property int|null $digest_day
 * @property int $digest_hour
 * @property bool $quiet_hours_enabled
 * @property string|null $quiet_hours_start
 * @property string|null $quiet_hours_end
 * @property string $timezone
 * @property \Illuminate\Support\Carbon|null $snoozed_until
 * @property int $quiet_hours_pending_count
 * @property \Illuminate\Support\Carbon|null $quiet_hours_last_summary_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereContentLanguages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereDigestDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereDigestFrequency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereDigestHour($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereFilters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereHideAchievements($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereHideComments($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereHideNsfw($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereLayout($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereNotificationPreferences($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference wherePushNotifications($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereQuietHoursEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereQuietHoursEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereQuietHoursLastSummaryAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereQuietHoursPendingCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereQuietHoursStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereSnoozedUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereSortBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereSortDir($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereTheme($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereTimezone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class UserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'layout',
        'theme',
        'sort_by',
        'sort_dir',
        'filters',
        'hide_nsfw',
        'hide_achievements',
        'hide_comments',
        'content_languages',
        'push_notifications',
        'notification_preferences',
        'digest_frequency',
        'digest_day',
        'digest_hour',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'timezone',
        'snoozed_until',
        'quiet_hours_pending_count',
        'quiet_hours_last_summary_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'hide_nsfw' => 'boolean',
        'hide_achievements' => 'boolean',
        'hide_comments' => 'boolean',
        'content_languages' => 'array',
        'push_notifications' => 'array',
        'notification_preferences' => 'array',
        'digest_day' => 'integer',
        'digest_hour' => 'integer',
        'quiet_hours_enabled' => 'boolean',
        'snoozed_until' => 'datetime',
        'quiet_hours_pending_count' => 'integer',
        'quiet_hours_last_summary_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Snooze notifications for a given number of hours.
     */
    public function snooze(int $hours): void
    {
        $this->snoozed_until = now()->addHours($hours);
        $this->save();
    }

    /**
     * Snooze until tomorrow at a specific hour (default 9:00 AM).
     */
    public function snoozeUntilTomorrow(int $hour = 9): void
    {
        $timezone = $this->timezone ?? 'Europe/Madrid';
        $this->snoozed_until = now($timezone)
            ->addDay()
            ->setTime($hour, 0, 0)
            ->setTimezone('UTC');
        $this->save();
    }

    /**
     * Cancel active snooze.
     */
    public function unsnooze(): void
    {
        $this->snoozed_until = null;
        $this->save();
    }

    /**
     * Check if snooze is currently active.
     */
    public function isSnoozed(): bool
    {
        return $this->snoozed_until !== null && $this->snoozed_until->isFuture();
    }

    /**
     * Get remaining snooze time in minutes.
     */
    public function getSnoozeRemainingMinutes(): ?int
    {
        if (! $this->isSnoozed()) {
            return null;
        }

        return (int) now()->diffInMinutes($this->snoozed_until, false);
    }

    /**
     * Increment the count of notifications received during quiet hours.
     */
    public function incrementQuietHoursPendingCount(): void
    {
        $this->increment('quiet_hours_pending_count');
    }

    /**
     * Reset the quiet hours pending count after sending summary.
     */
    public function resetQuietHoursPendingCount(): void
    {
        $this->quiet_hours_pending_count = 0;
        $this->quiet_hours_last_summary_at = now();
        $this->save();
    }

    /**
     * Check if user has pending quiet hours notifications.
     */
    public function hasQuietHoursPendingNotifications(): bool
    {
        return $this->quiet_hours_pending_count > 0;
    }
}
