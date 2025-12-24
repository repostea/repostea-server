<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalReport;
use App\Models\Role;
use App\Notifications\LegalReportReceivedNotification;
use App\Notifications\NewLegalReportNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

final class LegalReportController extends Controller
{
    /**
     * Submit a new legal report (DMCA, abuse, etc.).
     *
     * This endpoint does NOT require authentication - allows public reporting.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in([
                'copyright',
                'illegal',
                'harassment',
                'privacy',
                'spam',
                'other',
            ])],
            'content_url' => 'required|url|max:500',
            'reporter_name' => 'required|string|max:255',
            'reporter_email' => 'required|email|max:255',
            'reporter_organization' => 'nullable|string|max:255',
            'description' => 'required|string|max:5000',
            'original_url' => 'nullable|url|max:500',
            'ownership_proof' => 'nullable|string|max:2000',
            'good_faith' => 'required|boolean|accepted',
            'authorized' => 'nullable|boolean',
            'locale' => 'nullable|string|in:en,es',
            'cf-turnstile-response' => ['required', Rule::turnstile()],
        ]);

        // Copyright reports require authorized checkbox
        if ($validated['type'] === 'copyright' && ! ($validated['authorized'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization declaration is required for copyright reports.',
                'errors' => [
                    'authorized' => ['You must confirm you are authorized to file this copyright report.'],
                ],
            ], 422);
        }

        // Generate unique reference number
        $referenceNumber = $this->generateReferenceNumber();

        // Determine locale: use provided locale or detect from Accept-Language header
        $locale = $validated['locale'] ?? $this->detectLocale($request);

        // Create the legal report
        $report = LegalReport::create([
            'reference_number' => $referenceNumber,
            'type' => $validated['type'],
            'content_url' => $validated['content_url'],
            'reporter_name' => $validated['reporter_name'],
            'reporter_email' => $validated['reporter_email'],
            'reporter_organization' => $validated['reporter_organization'] ?? null,
            'description' => $validated['description'],
            'original_url' => $validated['original_url'] ?? null,
            'ownership_proof' => $validated['ownership_proof'] ?? null,
            'good_faith' => $validated['good_faith'],
            'authorized' => $validated['authorized'] ?? false,
            'status' => 'pending',
            'ip_address' => $request->ip(),
            'locale' => $locale,
        ]);

        // Notify admins about new legal report
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            foreach ($adminRole->users as $admin) {
                $admin->notify(new NewLegalReportNotification($report));
            }
        }

        // Send confirmation email to reporter
        Notification::route('mail', $report->reporter_email)
            ->notify(new LegalReportReceivedNotification($report));

        return response()->json([
            'success' => true,
            'message' => 'Your report has been submitted successfully. Our legal team will review it within 24-48 hours.',
            'data' => [
                'report_id' => $report->id,
                'reference_number' => $report->reference_number,
                'status' => $report->status,
            ],
        ], 201);
    }

    /**
     * Get report status by reference number (public endpoint).
     */
    public function status(Request $request)
    {
        $validated = $request->validate([
            'reference_number' => 'required|string',
            'email' => 'required|email',
        ]);

        // Validate reference number format (REP-YYYYMMDD-XXXX)
        $refNumber = $validated['reference_number'];
        if (! preg_match('/^REP-\d{8}-[A-F0-9]{4}$/i', $refNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reference number format.',
            ], 400);
        }

        $report = LegalReport::where('reference_number', $refNumber)
            ->where('reporter_email', $validated['email'])
            ->first();

        if (! $report) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found or email does not match.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'reference_number' => $report->reference_number,
                'status' => $report->status,
                'type' => $report->type,
                'submitted_at' => $report->created_at,
                'reviewed_at' => $report->reviewed_at,
                'user_response' => $report->user_response,
                'response_sent_at' => $report->response_sent_at,
            ],
        ]);
    }

    /**
     * Generate a unique reference number (format: REP-YYYYMMDD-XXXX).
     */
    private function generateReferenceNumber(): string
    {
        do {
            $date = date('Ymd');
            $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $referenceNumber = "REP-{$date}-{$random}";

            // Check if this reference number already exists
            $exists = LegalReport::where('reference_number', $referenceNumber)->exists();
        } while ($exists);

        return $referenceNumber;
    }

    /**
     * Detect locale from Accept-Language header.
     */
    private function detectLocale(Request $request): string
    {
        $acceptLanguage = $request->header('Accept-Language', 'en');

        // Simple detection: if contains 'es' anywhere, use Spanish, otherwise English
        if (str_contains(strtolower($acceptLanguage), 'es')) {
            return 'es';
        }

        return 'en';
    }
}
