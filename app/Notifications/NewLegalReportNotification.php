<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LegalReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class NewLegalReportNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly LegalReport $report,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $previousLocale = app()->getLocale();
        app()->setLocale($notifiable->locale ?? 'es');

        $data = [
            'title' => __('notifications.legal_report.new_title'),
            'body' => __('notifications.legal_report.new_body', [
                'type' => $this->report->type,
                'reference' => $this->report->reference_number,
            ]),
            'icon' => '⚖️',
            'type' => 'new_legal_report',
            'report_id' => $this->report->id,
            'reference_number' => $this->report->reference_number,
            'report_type' => $this->report->type,
            'action_url' => '/admin/legal-reports/' . $this->report->id,
        ];

        app()->setLocale($previousLocale);

        return $data;
    }
}
