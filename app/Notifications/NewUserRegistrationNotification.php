<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class NewUserRegistrationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly User $user,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // Set locale for this notification
        $previousLocale = app()->getLocale();
        app()->setLocale($notifiable->locale ?? 'es');

        $data = [
            'title' => __('notifications.new_user_registration_title'),
            'body' => __('notifications.new_user_registration_body', ['username' => $this->user->username]),
            'icon' => '👤',
            'type' => 'new_user_registration',
            'user_id' => $this->user->id,
            'username' => $this->user->username,
            'email' => $this->user->email,
            'registered_at' => $this->user->created_at->toISOString(),
            'action_url' => null, // No specific action needed
        ];

        // Restore previous locale
        app()->setLocale($previousLocale);

        return $data;
    }
}
