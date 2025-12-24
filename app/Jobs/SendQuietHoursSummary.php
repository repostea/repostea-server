<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Models\UserPreference;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\WebPushMessage;

final class SendQuietHoursSummary implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    /**
     * Execute the job.
     * This job runs every 15 minutes and checks for users whose quiet hours just ended.
     */
    public function handle(): void
    {
        // Find users with:
        // - quiet_hours_enabled = true
        // - quiet_hours_pending_count > 0
        // - NOT currently in quiet hours
        $preferences = UserPreference::query()
            ->where('quiet_hours_enabled', true)
            ->where('quiet_hours_pending_count', '>', 0)
            ->with('user')
            ->get();

        foreach ($preferences as $prefs) {
            $user = $prefs->user;

            if (! $user) {
                continue;
            }

            // Skip if still in quiet hours
            if ($user->isWithinQuietHours()) {
                continue;
            }

            // Skip if user is snoozed
            if ($user->isSnoozed()) {
                continue;
            }

            // Send summary push notification
            $this->sendSummaryPush($user, $prefs);
        }
    }

    /**
     * Send a summary push notification to the user.
     */
    private function sendSummaryPush(User $user, UserPreference $prefs): void
    {
        $count = $prefs->quiet_hours_pending_count;

        // Don't send if no pending notifications
        if ($count <= 0) {
            return;
        }

        // Check if user has push subscriptions
        $subscriptions = $user->pushSubscriptions;

        if ($subscriptions->isEmpty()) {
            // No subscriptions, just reset the counter
            $prefs->resetQuietHoursPendingCount();

            return;
        }

        try {
            // Build the message based on count
            $title = __('notifications.quiet_hours_summary.title');
            $body = trans_choice('notifications.quiet_hours_summary.body', $count, ['count' => $count]);

            $message = (new WebPushMessage())
                ->title($title)
                ->icon('/icons/icon-192x192.png')
                ->body($body)
                ->action(__('common.view'), 'view')
                ->data([
                    'url' => '/profile/notifications',
                    'type' => 'quiet_hours_summary',
                ])
                ->tag('quiet-hours-summary-' . $user->id);

            // Send to all user's subscriptions
            foreach ($subscriptions as $subscription) {
                try {
                    $subscription->sendNotification($message);
                } catch (Exception $e) {
                    Log::warning('Failed to send quiet hours summary to subscription', [
                        'user_id' => $user->id,
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Reset the counter after sending
            $prefs->resetQuietHoursPendingCount();

            Log::info('Sent quiet hours summary', [
                'user_id' => $user->id,
                'count' => $count,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send quiet hours summary', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
