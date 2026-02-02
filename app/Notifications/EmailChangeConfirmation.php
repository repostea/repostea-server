<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;

/**
 * Notification sent to the NEW email address with a confirmation link.
 * The user must click this link to complete the email change.
 */
final class EmailChangeConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $newEmail,
        private readonly string $token,
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

        $confirmationUrl = $this->getConfirmationUrl();

        return (new MailMessage())
            ->subject(Lang::get('notifications.email_change_confirmation.subject'))
            ->greeting(Lang::get('notifications.greeting'))
            ->line(Lang::get('notifications.email_change_confirmation.intro'))
            ->line(Lang::get('notifications.email_change_confirmation.instructions'))
            ->action(Lang::get('notifications.email_change_confirmation.action'), $confirmationUrl)
            ->line(Lang::get('notifications.email_change_confirmation.expires'))
            ->line(Lang::get('notifications.email_change_confirmation.not_you'))
            ->salutation(Lang::get('notifications.salutation'));
    }

    /**
     * Get the confirmation URL for the email change.
     */
    private function getConfirmationUrl(): string
    {
        $frontendUrl = Config::get('app.client_url', Config::get('app.url'));

        return $frontendUrl . '/profile/confirm-email-change?token=' . $this->token;
    }

    /**
     * Route notifications for the mail channel.
     * This ensures the notification is sent to the NEW email address.
     */
    public function routeNotificationForMail(): string
    {
        return $this->newEmail;
    }
}
