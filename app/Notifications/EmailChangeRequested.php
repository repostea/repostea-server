<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

/**
 * Notification sent to the CURRENT email address when a user
 * requests to change their email. This is a security warning.
 */
final class EmailChangeRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $newEmail,
    ) {}

    /**
     * Get the notification's channels.
     *
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        if (isset($notifiable->locale)) {
            App::setLocale($notifiable->locale);
        }

        return (new MailMessage())
            ->subject(Lang::get('notifications.email_change_requested.subject'))
            ->greeting(Lang::get('notifications.greeting'))
            ->line(Lang::get('notifications.email_change_requested.intro'))
            ->line(Lang::get('notifications.email_change_requested.new_email', ['email' => $this->newEmail]))
            ->line(Lang::get('notifications.email_change_requested.warning'))
            ->line(Lang::get('notifications.email_change_requested.not_you'))
            ->salutation(Lang::get('notifications.salutation'));
    }
}
