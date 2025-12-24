<?php

declare(strict_types=1);

namespace App\Notifications;

use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;

final class EmailVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The callback that should be used to create the verify email URL.
     *
     * @var Closure|null
     */
    public static $createUrlCallback;

    /**
     * The callback that should be used to build the mail message.
     *
     * @var Closure|null
     */
    public static $toMailCallback;

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

        $verificationUrl = $this->verificationUrl($notifiable);

        if (self::$toMailCallback !== null) {
            return call_user_func(self::$toMailCallback, $notifiable, $verificationUrl);
        }

        return $this->buildMailMessage($verificationUrl);
    }

    /**
     * Get the verify email notification mail message for the given URL.
     *
     * @param  string  $url
     *
     * @return MailMessage
     */
    protected function buildMailMessage($url)
    {
        return (new MailMessage())
            ->subject(Lang::get('notifications.email_verification.subject'))
            ->greeting(Lang::get('notifications.greeting'))
            ->line(Lang::get('notifications.email_verification.intro'))
            ->action(Lang::get('notifications.email_verification.action'), $url)
            ->line(Lang::get('notifications.email_verification.no_request'))
            ->salutation(Lang::get('notifications.salutation'));
    }

    /**
     * Get the verification URL for the given notifiable.
     *
     * @return string
     */
    protected function verificationUrl($notifiable)
    {
        if (self::$createUrlCallback !== null) {
            return call_user_func(self::$createUrlCallback, $notifiable);
        }

        // Force the URL to use SERVER_URL instead of APP_URL for email verification links
        // This ensures the link points to the backend API, not the frontend
        $previousUrl = Config::get('app.url');
        Config::set('app.url', Config::get('app.server_url', $previousUrl));

        // Generate backend verification URL with signed parameters
        $url = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        // Restore original APP_URL
        Config::set('app.url', $previousUrl);

        return $url;
    }

    /**
     * Set a callback that should be used when creating the email verification URL.
     *
     * @param  Closure  $callback
     */
    public static function createUrlUsing($callback): void
    {
        self::$createUrlCallback = $callback;
    }

    /**
     * Set a callback that should be used when building the notification mail message.
     *
     * @param  Closure  $callback
     */
    public static function toMailUsing($callback): void
    {
        self::$toMailCallback = $callback;
    }
}
