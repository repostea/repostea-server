<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

final class TestNotification extends Notification
{
    use Queueable;

    protected string $title;

    protected string $body;

    protected ?string $actionUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct(?string $title = null, ?string $body = null, ?string $actionUrl = null)
    {
        $this->title = $title ?? __('notifications.test.title');
        $this->body = $body ?? __('notifications.test.body');
        $this->actionUrl = $actionUrl ?? config('app.frontend_url');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
            'type' => 'test',
            'icon' => 'bell',
        ];
    }

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage())
            ->title($this->title)
            ->icon('/icons/icon-192x192.png')
            ->body($this->body)
            ->action(__('common.view'), 'view')
            ->options([
                'TTL' => 86400,
                'urgency' => 'normal',
            ])
            ->data([
                'url' => $this->actionUrl,
                'type' => 'test',
            ])
            ->tag('test-notification');
    }
}
