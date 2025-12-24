<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationViewTimestamp;
use App\Models\UserPreference;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NotificationChannels\WebPush\PushSubscription;

final class NotificationController extends Controller
{
    /**
     * Get VAPID public key for push subscription.
     */
    public function getVapidPublicKey(): JsonResponse
    {
        return response()->json([
            'public_key' => config('webpush.vapid.public_key'),
        ]);
    }

    /**
     * Save push subscription for the authenticated user.
     */
    public function savePushSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string|max:500',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        // Update or create subscription
        $user->updatePushSubscription(
            $request->input('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth'),
        );

        // Update device info on the subscription
        $subscription = PushSubscription::where('endpoint', $request->input('endpoint'))->first();
        if ($subscription) {
            $subscription->update([
                'user_agent' => $request->userAgent(),
                'device_name' => $request->input('device_name'),
            ]);
        }

        return response()->json([
            'message' => __('notifications.push_subscription_saved'),
            'subscription_count' => $user->pushSubscriptions()->count(),
        ]);
    }

    /**
     * Remove push subscription for the authenticated user.
     */
    public function removePushSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string',
        ]);

        $user = $request->user();
        $user->deletePushSubscription($request->input('endpoint'));

        return response()->json([
            'message' => __('notifications.push_subscription_removed'),
            'subscription_count' => $user->pushSubscriptions()->count(),
        ]);
    }

    /**
     * Get all push subscriptions for the authenticated user.
     */
    public function getPushSubscriptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscriptions = $user->pushSubscriptions()
            ->select('id', 'endpoint', 'user_agent', 'device_name', 'created_at')
            ->get()
            ->map(fn ($sub) => [
                'id' => $sub->id,
                'device_name' => $sub->device_name ?? $this->parseDeviceFromUserAgent($sub->user_agent),
                'created_at' => $sub->created_at->toISOString(),
            ]);

        return response()->json([
            'subscriptions' => $subscriptions,
        ]);
    }

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

    /**
     * Send a test push notification.
     */
    public function sendTestPush(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->pushSubscriptions()->count() === 0) {
            return response()->json([
                'message' => __('notifications.no_subscriptions'),
            ], 400);
        }

        $user->notify(new \App\Notifications\TestNotification());

        return response()->json([
            'message' => __('notifications.test_sent'),
        ]);
    }

    /**
     * Get notification summary grouped by type (Menéame style).
     * Returns only "new" notifications (created after last_viewed_at).
     */
    public function getSummary(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get last viewed timestamps for each category
        $viewTimestamps = NotificationViewTimestamp::where('user_id', $user->id)
            ->pluck('last_viewed_at', 'category');

        // Helper to map notification type to category
        $getCategory = static fn (string $type): string => match (true) {
            str_contains($type, 'PostCommented') => 'posts',
            str_contains($type, 'CommentReplied'),
            str_contains($type, 'AgoraMessageReplied') => 'comments',
            str_contains($type, 'UserMentioned'),
            str_contains($type, 'AgoraUserMentioned') => 'mentions',
            str_contains($type, 'AchievementUnlocked') => 'achievements',
            str_contains($type, 'KarmaLevelUp') => 'achievements',
            default => 'system',
        };

        // Get counts grouped by notification type (single query)
        $typeCounts = DB::table('notifications')
            ->selectRaw('type, COUNT(*) as total, SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread')
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->groupBy('type')
            ->get();

        // Pre-calculate "new" counts for all types in a single query
        // Build conditions for types that have a last_viewed timestamp
        $newCountsMap = collect();
        $typesWithTimestamp = $typeCounts->filter(function ($item) use ($getCategory, $viewTimestamps) {
            $category = $getCategory($item->type);

            return isset($viewTimestamps[$category]);
        });

        if ($typesWithTimestamp->isNotEmpty()) {
            // Build a union query for all types with timestamps
            $newCountsMap = DB::table('notifications')
                ->selectRaw('type, COUNT(*) as new_count')
                ->where('notifiable_type', get_class($user))
                ->where('notifiable_id', $user->id)
                ->whereNull('read_at')
                ->where(function ($query) use ($typesWithTimestamp, $getCategory, $viewTimestamps): void {
                    foreach ($typesWithTimestamp as $item) {
                        $category = $getCategory($item->type);
                        $lastViewed = $viewTimestamps[$category];
                        $query->orWhere(function ($q) use ($item, $lastViewed): void {
                            $q->where('type', $item->type)
                                ->where('created_at', '>', $lastViewed);
                        });
                    }
                })
                ->groupBy('type')
                ->pluck('new_count', 'type');
        }

        // Map type counts to category summary (no N+1 - all data pre-loaded)
        $summary = $typeCounts
            ->map(function ($item) use ($getCategory, $viewTimestamps, $newCountsMap) {
                $category = $getCategory($item->type);
                $lastViewed = $viewTimestamps[$category] ?? null;

                // Calculate new count without additional queries
                $newCount = $lastViewed === null
                    ? (int) $item->unread
                    : (int) ($newCountsMap[$item->type] ?? 0);

                return [
                    'category' => $category,
                    'total' => (int) $item->total,
                    'unread' => (int) $item->unread,
                    'new' => $newCount,
                ];
            })
            ->groupBy('category')
            ->map(static fn ($items) => [
                'total' => $items->sum('total'),
                'unread' => $items->sum('unread'),
                'new' => $items->sum('new'),
            ]);

        // Ensure all categories exist with zero counts
        $categories = ['posts', 'comments', 'mentions', 'achievements', 'system'];
        foreach ($categories as $category) {
            if (! isset($summary[$category])) {
                $summary[$category] = ['total' => 0, 'unread' => 0, 'new' => 0];
            }
        }

        // Add snooze status to summary
        $prefs = $user->preferences;

        return response()->json([
            'summary' => $summary,
            'total_unread' => $user->unreadNotifications()->count(),
            'is_snoozed' => $prefs?->isSnoozed() ?? false,
            'snoozed_until' => $prefs?->snoozed_until?->toISOString(),
        ]);
    }

    /**
     * Get all notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filter = $request->input('filter', 'all'); // all, unread, read
        $category = $request->input('category'); // posts, comments, mentions, achievements
        $perPage = $request->input('per_page', 20);

        // Build query
        $query = $user->notifications();

        // Apply category filter
        if ($category !== null) {
            if ($category === 'system') {
                // System category: all notifications that are NOT in other categories
                $excludedTypes = [
                    'App\\Notifications\\PostCommented',
                    'App\\Notifications\\CommentReplied',
                    'App\\Notifications\\AgoraMessageReplied',
                    'App\\Notifications\\UserMentioned',
                    'App\\Notifications\\AgoraUserMentioned',
                    'App\\Notifications\\AchievementUnlocked',
                    'App\\Notifications\\KarmaLevelUp',
                ];
                $query->whereNotIn('type', $excludedTypes);
            } else {
                $types = match ($category) {
                    'posts' => ['App\\Notifications\\PostCommented'],
                    'comments' => ['App\\Notifications\\CommentReplied', 'App\\Notifications\\AgoraMessageReplied'],
                    'mentions' => ['App\\Notifications\\UserMentioned', 'App\\Notifications\\AgoraUserMentioned'],
                    'achievements' => ['App\\Notifications\\AchievementUnlocked', 'App\\Notifications\\KarmaLevelUp'],
                    default => [],
                };

                if (count($types) > 0) {
                    $query->whereIn('type', $types);
                }
            }
        }

        // Apply read/unread filters
        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filter === 'read') {
            $query->whereNotNull('read_at');
        }

        // Get paginated notifications
        $notifications = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Transform notifications
        $transformedNotifications = $notifications->map(static function ($notification) {
            // Map notification class to type
            $type = match (true) {
                str_contains($notification->type, 'PostCommented') => 'comment',
                str_contains($notification->type, 'CommentReplied'),
                str_contains($notification->type, 'AgoraMessageReplied') => 'comment_reply',
                str_contains($notification->type, 'UserMentioned'),
                str_contains($notification->type, 'AgoraUserMentioned') => 'mention',
                str_contains($notification->type, 'AchievementUnlocked') => 'achievement',
                str_contains($notification->type, 'KarmaLevelUp') => 'karma_level',
                default => $notification->data['type'] ?? 'system',
            };

            return [
                'id' => $notification->id,
                'type' => $type,
                'title' => $notification->data['title'] ?? 'Notification',
                'body' => $notification->data['body'] ?? '',
                'read' => $notification->read_at !== null,
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at->toISOString(),
                'timestamp' => $notification->created_at->toISOString(),
                'iconClass' => $notification->data['iconClass'] ?? $notification->data['icon'] ?? null,
                'data' => $notification->data,
                'action_url' => $notification->data['action_url'] ?? null,
            ];
        });

        return response()->json([
            'data' => $transformedNotifications,
            'unread_count' => $user->unreadNotifications()->count(),
            'meta' => [
                'total' => $notifications->total(),
                'per_page' => $notifications->perPage(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->find($id);

        if ($notification === null) {
            return response()->json([
                'message' => __('messages.notifications.not_found'),
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => __('messages.notifications.marked_as_read'),
            'id' => $id,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'message' => __('messages.notifications.all_marked_as_read'),
            'unread_count' => 0,
        ]);
    }

    /**
     * Update last viewed timestamp for a notification category.
     */
    public function updateViewTimestamp(Request $request, string $category): JsonResponse
    {
        $user = $request->user();

        // Validate category
        $validCategories = ['posts', 'comments', 'mentions', 'achievements', 'system'];
        if (! in_array($category, $validCategories, true)) {
            return response()->json([
                'message' => __('messages.notifications.invalid_category'),
            ], 400);
        }

        // Update or create timestamp
        NotificationViewTimestamp::updateOrCreate(
            [
                'user_id' => $user->id,
                'category' => $category,
            ],
            [
                'last_viewed_at' => now(),
            ],
        );

        return response()->json([
            'message' => __('messages.notifications.view_timestamp_updated'),
            'category' => $category,
            'last_viewed_at' => now()->toISOString(),
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->find($id);

        if ($notification === null) {
            return response()->json([
                'message' => __('messages.notifications.not_found'),
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'message' => __('messages.notifications.deleted'),
            'id' => $id,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Delete all notifications for the authenticated user.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $user->notifications()->delete();

        return response()->json([
            'message' => __('messages.notifications.all_deleted'),
            'deleted_count' => $count,
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete notifications older than the specified days.
     */
    public function destroyOld(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = $request->input('days', 7); // Default 7 days

        $cutoffDate = now()->subDays($days);
        $count = $user->notifications()
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        return response()->json([
            'message' => __('messages.notifications.older_deleted', ['days' => $days]),
            'deleted_count' => $count,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Parse device name from user agent string.
     */
    private function parseDeviceFromUserAgent(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Unknown device';
        }

        // Simple detection for common browsers/platforms
        if (str_contains($userAgent, 'Chrome') && str_contains($userAgent, 'Mobile')) {
            return 'Chrome Mobile';
        }
        if (str_contains($userAgent, 'Firefox') && str_contains($userAgent, 'Mobile')) {
            return 'Firefox Mobile';
        }
        if (str_contains($userAgent, 'Safari') && str_contains($userAgent, 'Mobile')) {
            return 'Safari Mobile';
        }
        if (str_contains($userAgent, 'Chrome')) {
            return 'Chrome Desktop';
        }
        if (str_contains($userAgent, 'Firefox')) {
            return 'Firefox Desktop';
        }
        if (str_contains($userAgent, 'Safari')) {
            return 'Safari Desktop';
        }
        if (str_contains($userAgent, 'Edge')) {
            return 'Microsoft Edge';
        }

        return 'Web Browser';
    }
}
