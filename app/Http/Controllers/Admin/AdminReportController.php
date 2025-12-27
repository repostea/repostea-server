<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\Report;
use App\Models\ReportNote;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin controller for user report management.
 */
final class AdminReportController extends Controller
{
    use AuthorizesRequests;

    /**
     * List all reports.
     */
    public function index(Request $request)
    {
        $this->authorize('access-admin');

        $query = Report::with(['reportedBy', 'reportedUser', 'reportable', 'reviewedBy']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by reason
        if ($request->filled('reason')) {
            $query->where('reason', $request->get('reason'));
        }

        // Filter by type (reportable_type)
        if ($request->filled('type')) {
            $modelMap = [
                'post' => Post::class,
                'comment' => Comment::class,
                'user' => User::class,
            ];
            $type = $request->get('type');
            if (isset($modelMap[$type])) {
                $query->where('reportable_type', $modelMap[$type]);
            }
        }

        $reports = $query->latest()->paginate(50);

        return view('admin.reports.index', compact('reports'));
    }

    /**
     * View detailed information about a specific report.
     */
    public function show(Report $report)
    {
        $this->authorize('access-admin');

        // Load relationships
        $report->load([
            'reportable',
            'reportedBy',
            'reportedUser',
            'reviewedBy',
            'notes.user',
        ]);

        return view('admin.reports.view', compact('report'));
    }

    /**
     * Resolve a report.
     */
    public function resolve(Request $request, Report $report)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $report->resolve(Auth::id(), $validated['notes'] ?? null);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'resolve_report',
            targetUserId: $report->reported_user_id,
            metadata: ['report_id' => $report->id],
        );

        return redirect()->back()->with('success', 'Report has been resolved.');
    }

    /**
     * Dismiss a report.
     */
    public function dismiss(Request $request, Report $report)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $report->dismiss(Auth::id(), $validated['notes'] ?? null);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'dismiss_report',
            metadata: ['report_id' => $report->id],
        );

        return redirect()->back()->with('success', 'Report has been dismissed.');
    }

    /**
     * Reopen a resolved or dismissed report.
     */
    public function reopen(Request $request, Report $report)
    {
        $this->authorize('access-admin');

        // Check if report can be reopened
        if (! $report->canBeReopened()) {
            return redirect()->back()->with('error', 'Only resolved or dismissed reports can be reopened.');
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $report->reopen(Auth::id(), $validated['notes'] ?? null);

        ModerationLog::logAction(
            moderatorId: Auth::id(),
            action: 'reopen_report',
            targetUserId: $report->reported_user_id,
            metadata: ['report_id' => $report->id],
        );

        return redirect()->back()->with('success', 'Report has been reopened and is now pending review.');
    }

    /**
     * Add an internal note to a report.
     */
    public function addNote(Request $request, Report $report)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'note' => 'required|string|max:5000',
        ]);

        ReportNote::create([
            'report_id' => $report->id,
            'user_id' => Auth::id(),
            'note' => $validated['note'],
        ]);

        return redirect()->back()->with('success', 'Internal note added successfully.');
    }
}
