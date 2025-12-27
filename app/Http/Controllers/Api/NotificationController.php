<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationViewTimestamp;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for notification CRUD operations.
 * Push subscriptions are in PushSubscriptionController.
 * Notification preferences are in NotificationPreferencesController.
 */
final class NotificationController extends Controller
{
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
}
