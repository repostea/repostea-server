<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LegalReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

final class LegalReportReceivedNotification extends Notification implements ShouldQueue
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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        App::setLocale($this->report->locale ?? 'es');

        return (new MailMessage())
            ->subject(Lang::get('notifications.legal_report.received_subject'))
            ->greeting(Lang::get('notifications.greeting'))
            ->line(Lang::get('notifications.legal_report.received_intro', [
                'reference' => $this->report->reference_number,
            ]))
            ->line(Lang::get('notifications.legal_report.received_details', [
                'type' => $this->report->type,
            ]))
            ->line(Lang::get('notifications.legal_report.received_timeline'))
            ->salutation(Lang::get('notifications.salutation'));
    }
}
