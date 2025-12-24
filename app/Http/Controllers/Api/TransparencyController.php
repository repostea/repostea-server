<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransparencyStat;
use Illuminate\Http\JsonResponse;

final class TransparencyController extends Controller
{
    /**
     * Get the latest transparency statistics.
     */
    public function index(): JsonResponse
    {
        $stats = TransparencyStat::getLatest();

        if (! $stats) {
            return response()->json([
                'message' => 'No transparency statistics available yet',
                'data' => $this->getDefaultStats(),
            ]);
        }

        return response()->json([
            'data' => [
                'stats' => [
                    'posts' => $stats->total_posts,
                    'users' => $stats->total_users,
                    'comments' => $stats->total_comments,
                    'aggregated_sources' => $stats->total_aggregated_sources,
                ],
                'moderation' => [
                    'reports' => [
                        'total' => $stats->reports_total,
                        'processed' => $stats->reports_processed,
                        'pending' => $stats->reports_pending,
                    ],
                    'avg_response_hours' => $stats->avg_response_hours,
                    'actions' => [
                        'removed' => $stats->content_removed,
                        'warnings' => $stats->warnings_issued,
                        'suspended' => $stats->users_suspended,
                        'appeals' => $stats->appeals_total,
                    ],
                ],
                'report_types' => $this->formatReportTypes($stats->report_types ?? []),
                'calculated_at' => $stats->calculated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Format report types for frontend consumption.
     */
    private function formatReportTypes(array $reportTypes): array
    {
        $formatted = [];

        foreach ($reportTypes as $type => $count) {
            $formatted[] = [
                'type' => $type,
                'count' => $count,
            ];
        }

        return $formatted;
    }

    /**
     * Get default stats when none are available.
     */
    private function getDefaultStats(): array
    {
        return [
            'stats' => [
                'posts' => 0,
                'users' => 0,
                'comments' => 0,
                'aggregated_sources' => 0,
            ],
            'moderation' => [
                'reports' => [
                    'total' => 0,
                    'processed' => 0,
                    'pending' => 0,
                ],
                'avg_response_hours' => 0,
                'actions' => [
                    'removed' => 0,
                    'warnings' => 0,
                    'suspended' => 0,
                    'appeals' => 0,
                ],
            ],
            'report_types' => [],
        ];
    }
}
