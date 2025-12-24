<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

final class AccountRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly string $rejectionReason,
    ) {}

    /**
     * Get the notification's channels.
     *
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        if (isset($notifiable->locale)) {
            App::setLocale($notifiable->locale);
        }

        return (new MailMessage())
            ->subject(Lang::get('notifications.account_approval.rejected.subject'))
            ->greeting(Lang::get('notifications.greeting'))
            ->line(Lang::get('notifications.account_approval.rejected.intro'))
            ->line(Lang::get('notifications.account_approval.rejected.reason_label'))
            ->line('**' . $this->rejectionReason . '**')
            ->line(Lang::get('notifications.account_approval.rejected.contact'))
            ->salutation(Lang::get('notifications.salutation'));
    }
}
