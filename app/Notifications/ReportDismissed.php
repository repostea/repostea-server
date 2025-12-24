<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class ReportDismissed extends Notification
{
    use Queueable;

    public function __construct(
        protected Report $report,
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
        return [
            'title' => __('notifications.report_dismissed.title'),
            'body' => __('notifications.report_dismissed.body'),
            'type' => 'report_dismissed',
            'report_id' => $this->report->id,
            'icon' => 'x-circle',
        ];
    }
}
