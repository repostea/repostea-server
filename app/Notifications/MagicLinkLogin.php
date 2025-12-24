<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

final class MagicLinkLogin extends Notification
{
    use Queueable;

    /**
     * The magic link URL.
     *
     * @var string
     */
    protected $magicLink;

    /**
     * Create a new notification instance.
     *
     * @param  string  $magicLink
     */
    public function __construct($magicLink)
    {
        $this->magicLink = $magicLink;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @return MailMessage
     */
    public function toMail($notifiable)
    {

        if (isset($notifiable->locale)) {
            App::setLocale($notifiable->locale);
        }

        return (new MailMessage())
            ->subject(Lang::get('notifications.magic_link.subject'))
            ->greeting(Lang::get('notifications.greeting'))
            ->line(Lang::get('notifications.magic_link.intro'))
            ->action(Lang::get('notifications.magic_link.action'), $this->magicLink)
            ->line(Lang::get('notifications.magic_link.expiration'))
            ->line(Lang::get('notifications.magic_link.no_request'))
            ->salutation(Lang::get('notifications.salutation'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'magic_link' => $this->magicLink,
        ];
    }
}
