<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

final class ReportController extends Controller
{
    /**
     * Submit a new report.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reportable_type' => ['required', 'string', Rule::in(['post', 'comment', 'user'])],
            'reportable_id' => 'required|integer',
            'reason' => ['required', 'string', Rule::in([
                'spam',
                'harassment',
                'inappropriate',
                'misinformation',
                'hate_speech',
                'violence',
                'illegal_content',
                'copyright',
                'other',
            ])],
            'description' => 'nullable|string|max:1000',
        ]);

        // Map to full model class name
        $modelMap = [
            'post' => Post::class,
            'comment' => Comment::class,
            'user' => User::class,
        ];

        $reportableType = $modelMap[$validated['reportable_type']];

        // Check if the reportable exists
        $reportable = $reportableType::find($validated['reportable_id']);

        if (! $reportable) {
            return response()->json([
                'error' => 'Content not found',
            ], 404);
        }

        // Check if user already reported this content
        $existingReport = Report::where('reported_by', Auth::id())
            ->where('reportable_type', $reportableType)
            ->where('reportable_id', $validated['reportable_id'])
            ->where('created_at', '>=', now()->subDays(30))
            ->first();

        if ($existingReport) {
            return response()->json([
                'error' => 'You have already reported this content',
                'report' => $existingReport,
            ], 422);
        }

        // Determine reported user
        $reportedUserId = null;
        if ($validated['reportable_type'] === 'user') {
            $reportedUserId = $validated['reportable_id'];
        } elseif ($validated['reportable_type'] === 'post') {
            $reportedUserId = $reportable->user_id;
        } elseif ($validated['reportable_type'] === 'comment') {
            $reportedUserId = $reportable->user_id;
        }

        // Create the report
        $report = Report::create([
            'reported_by' => Auth::id(),
            'reported_user_id' => $reportedUserId,
            'reportable_type' => $reportableType,
            'reportable_id' => $validated['reportable_id'],
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Report submitted successfully. Our moderation team will review it.',
            'report' => $report,
        ], 201);
    }

    /**
     * Get user's reports.
     */
    public function index(Request $request)
    {
        $reports = Report::where('reported_by', Auth::id())
            ->with(['reportable'])
            ->latest()
            ->paginate(20);

        return response()->json($reports);
    }

    /**
     * Get a specific report.
     */
    public function show(Report $report)
    {
        // Only allow viewing own reports or moderators
        if ($report->reported_by !== Auth::id() && ! Auth::user()->isModerator()) {
            return response()->json([
                'error' => 'Unauthorized',
            ], 403);
        }

        $report->load(['reportable', 'reportedBy', 'reportedUser', 'reviewedBy']);

        return response()->json($report);
    }

    /**
     * Delete a report (only if it's the user's own report and not resolved yet).
     */
    public function destroy(Report $report)
    {
        // Only allow deleting own reports
        if ($report->reported_by !== Auth::id()) {
            return response()->json([
                'error' => 'You can only delete your own reports',
            ], 403);
        }

        // Only allow deleting if not resolved or dismissed
        if ($report->status === 'resolved' || $report->status === 'dismissed') {
            return response()->json([
                'error' => 'Cannot delete a report that has already been reviewed',
            ], 422);
        }

        $report->delete();

        return response()->json([
            'message' => 'Report deleted successfully',
        ]);
    }
}
