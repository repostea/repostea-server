<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\LegalReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class LegalReportResolutionMail extends Mailable
{
    use Queueable;

    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public LegalReport $legalReport,
        ?string $localeOverride = null,
    ) {
        // Use the provided locale override, or fall back to the report's locale
        $this->locale = $localeOverride ?? $legalReport->locale ?? 'en';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Translate status text based on the selected locale
        $statusText = $this->legalReport->status === 'resolved'
            ? __('legal.legal_report.status_resolved', [], $this->locale)
            : __('legal.legal_report.status_rejected', [], $this->locale);

        return new Envelope(
            subject: __('legal.legal_report.email_subject', [
                'reference' => $this->legalReport->reference_number,
                'status' => $statusText,
            ], $this->locale),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.legal-report-resolution',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
