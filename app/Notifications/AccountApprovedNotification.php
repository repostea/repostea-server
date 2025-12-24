<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

final class AccountApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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

        $locale = $notifiable->locale ?? 'es';
        $loginUrl = config('app.frontend_url') ? config('app.frontend_url') . "/{$locale}/auth/login" : url('/login');

        return (new MailMessage())
            ->subject(Lang::get('notifications.account_approval.approved.subject'))
            ->greeting(Lang::get('notifications.greeting'))
            ->line(Lang::get('notifications.account_approval.approved.intro'))
            ->line(Lang::get('notifications.account_approval.approved.next_steps'))
            ->action(Lang::get('notifications.account_approval.approved.action'), $loginUrl)
            ->line(Lang::get('notifications.account_approval.approved.welcome'))
            ->salutation(Lang::get('notifications.salutation'));
    }
}
