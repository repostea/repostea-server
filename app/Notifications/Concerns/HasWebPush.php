<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Models\User;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

trait HasWebPush
{
    /**
     * Get the category for push notification preferences.
     * Override this in child classes.
     */
    abstract protected function getPushCategory(): string;

    /**
     * Get the push notification title.
     * Override this in child classes.
     */
    abstract protected function getPushTitle(): string;

    /**
     * Get the push notification body.
     * Override this in child classes.
     */
    abstract protected function getPushBody(): string;

    /**
     * Get the push notification URL.
     * Override this in child classes.
     */
    abstract protected function getPushUrl(): string;

    /**
     * Determine if WebPush channel should be included.
     * If blocked by quiet hours, increment the pending count for summary later.
     */
    protected function shouldSendWebPush(object $notifiable): bool
    {
        if (! $notifiable instanceof User) {
            return false;
        }

        // Check if would be blocked by quiet hours (but not snooze)
        if ($notifiable->isWithinQuietHours() && ! $notifiable->isSnoozed()) {
            // Increment pending count for summary notification later
            $prefs = $notifiable->preferences;
            if ($prefs) {
                $prefs->incrementQuietHoursPendingCount();
            }

            return false;
        }

        return $notifiable->shouldReceiveInstantPush($this->getPushCategory());
    }

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage())
            ->title($this->getPushTitle())
            ->icon('/icons/icon-192x192.png')
            ->body($this->getPushBody())
            ->action(__('common.view'), 'view')
            ->data([
                'url' => $this->getPushUrl(),
                'type' => $this->getPushCategory(),
            ])
            ->tag($this->getPushCategory() . '-' . ($notification->id ?? uniqid()));
    }

    /**
     * Get channels including WebPush if applicable.
     */
    protected function getChannelsWithWebPush(object $notifiable, array $baseChannels = ['database']): array
    {
        if ($this->shouldSendWebPush($notifiable)) {
            $baseChannels[] = WebPushChannel::class;
        }

        return $baseChannels;
    }
}
