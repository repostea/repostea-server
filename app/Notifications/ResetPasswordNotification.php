<?php

declare(strict_types=1);

namespace App\Notifications;

use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

final class ResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * The password reset token.
     *
     * @var string
     */
    public $token;

    /**
     * The callback that should be used to create the reset password URL.
     *
     * @var (Closure(mixed, string): string)|null
     */
    public static $createUrlCallback;

    /**
     * The callback that should be used to build the mail message.
     *
     * @var (Closure(mixed, string): MailMessage)|null
     */
    public static $toMailCallback;

    /**
     * Create a notification instance.
     *
     * @param  string  $token
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

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

        if (self::$toMailCallback !== null) {
            return call_user_func(self::$toMailCallback, $notifiable, $this->token);
        }

        return $this->buildMailMessage($this->resetUrl($notifiable));
    }

    /**
     * Get the reset password notification mail message for the given URL.
     *
     * @param  string  $url
     *
     * @return MailMessage
     */
    protected function buildMailMessage($url)
    {
        return (new MailMessage())
            ->subject(Lang::get('notifications.password_reset.subject'))
            ->greeting(Lang::get('notifications.greeting'))
            ->line(Lang::get('notifications.password_reset.intro'))
            ->action(Lang::get('notifications.password_reset.action'), $url)
            ->line(Lang::get('notifications.password_reset.expiration', ['count' => config('auth.passwords.' . config('auth.defaults.passwords') . '.expire')]))
            ->line(Lang::get('notifications.password_reset.no_request'))
            ->salutation(Lang::get('notifications.salutation'));
    }

    /**
     * Get the reset URL for the given notifiable.
     *
     * @return string
     */
    protected function resetUrl($notifiable)
    {
        if (self::$createUrlCallback !== null) {
            return call_user_func(self::$createUrlCallback, $notifiable, $this->token);
        }

        $locale = $notifiable->locale ?? 'es';

        return config('app.frontend_url') . "/{$locale}/auth/reset-password/" . $this->token . '?email=' . urlencode($notifiable->getEmailForPasswordReset());
    }

    /**
     * Set a callback that should be used when creating the reset password button URL.
     *
     * @param  Closure(mixed, string): string  $callback
     */
    public static function createUrlUsing($callback): void
    {
        self::$createUrlCallback = $callback;
    }

    /**
     * Set a callback that should be used when building the notification mail message.
     *
     * @param  Closure(mixed, string): MailMessage  $callback
     */
    public static function toMailUsing($callback): void
    {
        self::$toMailCallback = $callback;
    }
}
