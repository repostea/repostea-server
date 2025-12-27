<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Helpers\ErrorHelper;
use App\Http\Controllers\Controller;
use App\Models\LegalReport;
use App\Models\LegalReportNote;
use App\Models\LegalReportNotification;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Admin controller for legal report management (DMCA, abuse, etc.).
 */
final class AdminLegalReportController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show legal reports list.
     */
    public function index(Request $request)
    {
        $this->authorize('access-admin');

        $query = LegalReport::query();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        // Search by reference number
        if ($request->filled('reference')) {
            $query->where('reference_number', 'like', '%' . $request->get('reference') . '%');
        }

        $reports = $query->latest()->paginate(50);

        return view('admin.legal-reports.index', compact('reports'));
    }

    /**
     * Show a specific legal report.
     */
    public function show(LegalReport $legalReport)
    {
        $this->authorize('access-admin');

        // Load notes with user relationship, notification sender, and all notifications
        $legalReport->load(['notes.user', 'notificationSender', 'notifications.sender']);

        return view('admin.legal-reports.view', compact('legalReport'));
    }

    /**
     * Update legal report status.
     */
    public function updateStatus(Request $request, LegalReport $legalReport)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'status' => 'required|in:pending,under_review,resolved,rejected',
            'user_response' => 'nullable|string|max:5000',
        ]);

        $updateData = [
            'status' => $validated['status'],
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ];

        // If user_response is provided, save it and mark when it was sent
        if ($request->filled('user_response')) {
            $updateData['user_response'] = $validated['user_response'];
            $updateData['response_sent_at'] = now();
        }

        $legalReport->update($updateData);

        return redirect()->back()->with('success', 'Legal report status updated successfully.');
    }

    /**
     * Add an internal note to a legal report.
     */
    public function addNote(Request $request, LegalReport $legalReport)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'note' => 'required|string|max:5000',
        ]);

        LegalReportNote::create([
            'legal_report_id' => $legalReport->id,
            'user_id' => Auth::id(),
            'note' => $validated['note'],
        ]);

        return redirect()->back()->with('success', 'Internal note added successfully.');
    }

    /**
     * Send notification email to the reporter about the resolution.
     */
    public function notify(Request $request, LegalReport $legalReport)
    {
        $this->authorize('access-admin');

        // Verify there's a response and the status is resolved or rejected
        if (! $legalReport->user_response || ! in_array($legalReport->status, ['resolved', 'rejected'])) {
            return redirect()->back()->with('error', 'Cannot send notification: report must have a response and be resolved or rejected.');
        }

        // Validate the selected locale
        $validated = $request->validate([
            'locale' => 'required|in:en,es',
        ]);

        // Create a notification record with "sending" status
        $notification = LegalReportNotification::create([
            'legal_report_id' => $legalReport->id,
            'sent_by' => Auth::id(),
            'locale' => $validated['locale'],
            'content' => $legalReport->user_response,
            'status' => 'sending',
            'recipient_email' => $legalReport->reporter_email,
        ]);

        // Update legal_reports fields to maintain last notification info
        $sendingData = [
            'notification_sent_at' => now(),
            'notification_sent_by' => Auth::id(),
            'notification_locale' => $validated['locale'],
            'notification_content' => $legalReport->user_response,
            'notification_status' => 'sending',
            'notification_error' => null,
        ];

        // Also update response_sent_at if not already set
        if (! $legalReport->response_sent_at) {
            $sendingData['response_sent_at'] = now();
        }

        $legalReport->update($sendingData);

        try {
            // Send the email with the selected locale
            Mail::to($legalReport->reporter_email)->send(new \App\Mail\LegalReportResolutionMail($legalReport, $validated['locale']));

            // Mark notification as sent successfully
            $notification->update(['status' => 'sent']);

            // Update legal_report as well
            $legalReport->update([
                'notification_status' => 'sent',
            ]);

            $languageName = $validated['locale'] === 'es' ? 'Spanish' : 'English';

            return redirect()->back()->with('success', 'Notification email sent successfully to ' . $legalReport->reporter_email . ' in ' . $languageName);
        } catch (Exception $e) {
            Log::error('Error sending legal report notification: ' . $e->getMessage());

            // Mark notification as failed
            $notification->update([
                'status' => 'failed',
                'error_message' => ErrorHelper::getSafeError($e),
            ]);

            // Update legal_report as well
            $legalReport->update([
                'notification_status' => 'failed',
                'notification_error' => ErrorHelper::getSafeError($e),
            ]);

            return redirect()->back()->with('error', __('messages.admin.notification_error'));
        }
    }
}
