<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Generic push notification that wraps a WebPushMessage.
 */
final class PushNotification extends Notification
{
    use Queueable;

    protected WebPushMessage $message;

    /**
     * Create a new notification instance.
     */
    public function __construct(WebPushMessage $message)
    {
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return $this->message;
    }
}
