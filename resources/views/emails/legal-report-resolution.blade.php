<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('legal.legal_report.email_title') }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 3px solid #4a5568;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            color: #2d3748;
            font-size: 24px;
        }
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 10px;
        }
        .badge-resolved {
            background-color: #c6f6d5;
            color: #22543d;
        }
        .badge-rejected {
            background-color: #fed7d7;
            color: #742a2a;
        }
        .content {
            margin-bottom: 30px;
        }
        .info-box {
            background-color: #f7fafc;
            border-left: 4px solid #4299e1;
            padding: 15px;
            margin: 20px 0;
        }
        .info-label {
            font-weight: 600;
            color: #4a5568;
            font-size: 14px;
        }
        .info-value {
            color: #2d3748;
            margin-top: 5px;
        }
        .response-box {
            background-color: #edf2f7;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
            margin-top: 30px;
            font-size: 14px;
            color: #718096;
        }
        .reference {
            font-family: 'Courier New', monospace;
            background-color: #edf2f7;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ __('legal.legal_report.email_title') }}</h1>
            <div class="reference">{{ __('legal.legal_report.reference') }}: {{ $legalReport->reference_number }}</div>
            <div>
                @if($legalReport->status === 'resolved')
                    <span class="badge badge-resolved">{{ __('legal.legal_report.status_resolved') }}</span>
                @else
                    <span class="badge badge-rejected">{{ __('legal.legal_report.status_rejected') }}</span>
                @endif
            </div>
        </div>

        <div class="content">
            <p>{{ __('legal.legal_report.greeting', ['name' => $legalReport->reporter_name]) }}</p>

            <p>{{ $legalReport->status === 'resolved' ? __('legal.legal_report.resolved_message') : __('legal.legal_report.rejected_message') }}</p>

            <div class="info-box">
                <div class="info-label">{{ __('legal.legal_report.report_type') }}:</div>
                <div class="info-value">{{ ucfirst($legalReport->type) }}</div>
            </div>

            <div class="info-box">
                <div class="info-label">{{ __('legal.legal_report.reported_content') }}:</div>
                <div class="info-value"><a href="{{ $legalReport->content_url }}" style="color: #4299e1;">{{ $legalReport->content_url }}</a></div>
            </div>

            @if($legalReport->user_response)
                <div class="response-box">
                    <div class="info-label">{{ __('legal.legal_report.team_response') }}:</div>
                    <div class="info-value" style="margin-top: 10px; white-space: pre-wrap;">{{ $legalReport->user_response }}</div>
                </div>
            @endif

            <p style="margin-top: 20px;">{{ __('legal.legal_report.thank_you') }}</p>
        </div>

        <div class="footer">
            <p><strong>{{ __('legal.legal_report.automated_email') }}</strong></p>
            <p>{{ __('legal.legal_report.additional_questions') }}</p>
            <p style="margin-top: 15px; font-size: 12px;">
                {{ __('legal.legal_report.review_date') }}: {{ $legalReport->reviewed_at ? $legalReport->reviewed_at->format('d/m/Y H:i') : 'N/A' }}
            </p>
        </div>
    </div>
</body>
</html>
