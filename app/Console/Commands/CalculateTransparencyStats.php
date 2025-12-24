<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Comment;
use App\Models\Post;
use App\Models\TransparencyStat;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class CalculateTransparencyStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transparency:calculate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and store transparency statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Calculating transparency statistics...');

        // Calculate general statistics
        $totalPosts = Post::count();
        $totalUsers = User::count();
        $totalComments = Comment::count();
        $totalAggregatedSources = 1; // We only have Mbin for now

        $this->info("  Posts: {$totalPosts}, Users: {$totalUsers}, Comments: {$totalComments}");

        // Calculate moderation reports (last 30 days)
        $reportsTotal = DB::table('reports')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $reportsProcessed = DB::table('reports')
            ->where('created_at', '>=', now()->subDays(30))
            ->whereIn('status', ['resolved', 'dismissed'])
            ->count();

        $reportsPending = DB::table('reports')
            ->whereIn('status', ['pending', 'reviewing'])
            ->count();

        // Calculate average response time (in hours)
        $avgResponseHours = (float) (DB::table('reports')
            ->whereNotNull('reviewed_at')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, reviewed_at)) as avg_hours')
            ->value('avg_hours') ?? 0);

        $this->info("  Reports: Total={$reportsTotal}, Processed={$reportsProcessed}, Pending={$reportsPending}, AvgResponse={$avgResponseHours}h");

        // Calculate moderation actions (last 30 days)
        $contentRemoved = DB::table('moderation_logs')
            ->where('created_at', '>=', now()->subDays(30))
            ->whereIn('action', ['delete_post', 'delete_comment', 'unpublish_post'])
            ->count();

        $warningsIssued = DB::table('user_strikes')
            ->where('created_at', '>=', now()->subDays(30))
            ->where('type', 'warning')
            ->count();

        $usersSuspended = DB::table('user_bans')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $appealsTotal = DB::table('moderation_logs')
            ->where('created_at', '>=', now()->subDays(30))
            ->where('action', 'appeal')
            ->count();

        $this->info("  Actions: Removed={$contentRemoved}, Warnings={$warningsIssued}, Suspended={$usersSuspended}, Appeals={$appealsTotal}");

        // Calculate report types breakdown (last 30 days)
        $reportTypes = DB::table('reports')
            ->where('created_at', '>=', now()->subDays(30))
            ->select('reason', DB::raw('count(*) as count'))
            ->groupBy('reason')
            ->get()
            ->pluck('count', 'reason')
            ->toArray();

        $this->info('  Report types: ' . json_encode($reportTypes));

        // Create new stats record
        $stats = TransparencyStat::create([
            'total_posts' => $totalPosts,
            'total_users' => $totalUsers,
            'total_comments' => $totalComments,
            'total_aggregated_sources' => $totalAggregatedSources,
            'reports_total' => $reportsTotal,
            'reports_processed' => $reportsProcessed,
            'reports_pending' => $reportsPending,
            'avg_response_hours' => (int) round($avgResponseHours),
            'content_removed' => $contentRemoved,
            'warnings_issued' => $warningsIssued,
            'users_suspended' => $usersSuspended,
            'appeals_total' => $appealsTotal,
            'report_types' => $reportTypes,
            'calculated_at' => now(),
        ]);

        $this->info("✓ Transparency stats calculated and saved (ID: {$stats->id})");

        // Clean up old stats (keep only last 30 days)
        $deleted = TransparencyStat::where('calculated_at', '<', now()->subDays(30))->delete();
        if ($deleted > 0) {
            $this->info("  Cleaned up {$deleted} old stats records");
        }

        return self::SUCCESS;
    }
}
