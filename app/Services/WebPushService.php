<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\WebPushMessage;

final class WebPushService
{
    /**
     * Check if user is currently snoozed.
     */
    public function isSnoozed(User $user): bool
    {
        return $user->isSnoozed();
    }

    /**
     * Check if current time is within user's quiet hours.
     */
    public function isWithinQuietHours(User $user): bool
    {
        return $user->isWithinQuietHours();
    }

    /**
     * Check if user should receive instant push notification for a category.
     */
    public function shouldSendInstant(User $user, string $category): bool
    {
        return $user->shouldReceiveInstantPush($category);
    }

    /**
     * Send push notification to user.
     *
     * @param  array{title: string, body: string, icon?: string, url?: string, tag?: string}  $payload
     */
    public function sendToUser(User $user, array $payload, string $category): bool
    {
        // Check if user should receive this notification
        if (! $this->shouldSendInstant($user, $category)) {
            Log::debug('Push notification blocked by preferences', [
                'user_id' => $user->id,
                'category' => $category,
            ]);

            return false;
        }

        // Check if user has active subscriptions
        $subscriptions = $user->pushSubscriptions;
        if ($subscriptions->isEmpty()) {
            Log::debug('No push subscriptions for user', ['user_id' => $user->id]);

            return false;
        }

        // Build the message
        $message = (new WebPushMessage())
            ->title($payload['title'])
            ->body($payload['body'])
            ->icon($payload['icon'] ?? '/icons/icon-192x192.png')
            ->data(['url' => $payload['url'] ?? '/'])
            ->tag($payload['tag'] ?? $category);

        // Send to all subscriptions
        try {
            foreach ($subscriptions as $subscription) {
                $user->notify(new \App\Notifications\PushNotification($message));
            }

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send push notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Snooze notifications for a user.
     */
    public function snoozeNotifications(User $user, int $hours): void
    {
        $prefs = $user->preferences;
        if ($prefs) {
            $prefs->snooze($hours);
        }
    }

    /**
     * Snooze notifications until tomorrow morning.
     */
    public function snoozeUntilTomorrow(User $user, int $hour = 9): void
    {
        $prefs = $user->preferences;
        if ($prefs) {
            $prefs->snoozeUntilTomorrow($hour);
        }
    }

    /**
     * Cancel snooze for a user.
     */
    public function unsnooze(User $user): void
    {
        $prefs = $user->preferences;
        if ($prefs) {
            $prefs->unsnooze();
        }
    }

    /**
     * Clean up expired push subscriptions.
     * This should be called periodically (e.g., monthly) to remove
     * subscriptions that are no longer valid.
     */
    public function cleanupExpiredSubscriptions(): int
    {
        // Delete subscriptions older than 6 months directly (single query, no N+1)
        return \NotificationChannels\WebPush\PushSubscription::where(
            'created_at',
            '<',
            Carbon::now()->subMonths(6),
        )->delete();
    }
}
