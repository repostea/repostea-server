<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\KarmaEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class KarmaEventStarting extends Notification implements ShouldQueue
{
    use Queueable;

    protected $event;

    /**
     * Create a new notification instance.
     */
    public function __construct(KarmaEvent $event)
    {
        $this->event = $event;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // In-app notifications only (bell icon + real-time)
        return ['database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $multiplier = $this->event->multiplier;
        $startTime = $this->event->start_at->format('H:i');
        $endTime = $this->event->end_at->format('H:i');
        $date = $this->event->start_at->format('d/m/Y');

        $locale = $notifiable->locale ?? 'es';
        $homeUrl = config('app.client_url') ? config('app.client_url') . "/{$locale}/" : url('/');
        $eventTypeName = __('notifications.karma_event_types.' . $this->event->type, [], $locale);

        return (new MailMessage())
            ->subject(__('notifications.karma_event_starting_subject', ['event' => $eventTypeName], $locale))
            ->greeting(__('notifications.greeting', [], $locale) . " {$notifiable->username}!")
            ->line(__('notifications.karma_event_starting_intro', [], $locale))
            ->line("{$this->event->description}")
            ->line(__('notifications.karma_event_multiplier', ['multiplier' => $multiplier], $locale))
            ->line(__('notifications.karma_event_time', ['date' => $date, 'start' => $startTime, 'end' => $endTime], $locale))
            ->action(__('notifications.participate_now', [], $locale), $homeUrl)
            ->line(__('notifications.karma_event_opportunity', [], $locale));
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $locale = $notifiable->locale ?? 'es';
        $multiplier = $this->event->multiplier;
        $eventTypeName = __('notifications.karma_event_types.' . $this->event->type, [], $locale);
        $startTime = $this->event->start_at->locale($locale)->isoFormat('D MMMM HH:mm');

        return [
            'title' => '🌊 ' . __('notifications.karma_event_title', ['event' => $eventTypeName], $locale),
            'body' => __('notifications.karma_event_body', ['multiplier' => $multiplier, 'time' => $startTime], $locale),
            'icon' => '🌊',
            'action_url' => null,
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'type' => $this->event->type,
            'description' => $this->event->description,
            'multiplier' => $this->event->multiplier,
            'start_at' => $this->event->start_at->toIso8601String(),
            'end_at' => $this->event->end_at->toIso8601String(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * @return array
     */
    public function toBroadcast($notifiable)
    {
        $locale = $notifiable->locale ?? 'es';
        $eventTypeName = __('notifications.karma_event_types.' . $this->event->type, [], $locale);

        return [
            'event_id' => $this->event->id,
            'title' => __('notifications.karma_event_title', ['event' => $eventTypeName], $locale),
            'body' => $this->event->description,
            'multiplier' => $this->event->multiplier,
            'start_at' => $this->event->start_at->toIso8601String(),
            'end_at' => $this->event->end_at->toIso8601String(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
