<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RateLimitLog;
use Illuminate\Console\Command;

final class RateLimitStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rate-limit:stats
                            {--hours=24 : Time range in hours}
                            {--action= : Filter by specific action}
                            {--top=10 : Number of top offenders to show}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display rate limit violation statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $action = $this->option('action');
        $top = (int) $this->option('top');

        $this->info("Rate Limit Statistics - Last {$hours} hours");
        $this->line(str_repeat('=', 60));
        $this->newLine();

        // Overall statistics
        $query = RateLimitLog::where('created_at', '>=', now()->subHours($hours));

        if ($action) {
            $query->where('action', $action);
            $this->info("Filtered by action: {$action}");
            $this->newLine();
        }

        $totalViolations = $query->count();
        $uniqueUsers = $query->whereNotNull('user_id')->distinct('user_id')->count('user_id');
        $uniqueIps = $query->distinct('ip_address')->count('ip_address');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Violations', number_format($totalViolations)],
                ['Unique Users', number_format($uniqueUsers)],
                ['Unique IPs', number_format($uniqueIps)],
            ],
        );

        $this->newLine();

        // Violations by action
        if (! $action) {
            $this->info('Violations by Action Type:');
            $violationsByAction = RateLimitLog::getViolationsByAction($hours);

            if ($violationsByAction->isEmpty()) {
                $this->warn('No violations found in the specified time range.');
            } else {
                $this->table(
                    ['Action', 'Total Violations', 'Unique Users', 'Unique IPs'],
                    $violationsByAction->map(fn ($v) => [
                        $v->action,
                        number_format($v->total_violations),
                        number_format($v->unique_users),
                        number_format($v->unique_ips),
                    ])->toArray(),
                );
            }

            $this->newLine();
        }

        // Top offending users
        $this->info("Top {$top} Offending Users:");
        $topOffenders = RateLimitLog::select('user_id')
            ->selectRaw('COUNT(*) as violation_count')
            ->selectRaw('COUNT(DISTINCT action) as unique_actions')
            ->whereNotNull('user_id')
            ->where('created_at', '>=', now()->subHours($hours))
            ->groupBy('user_id')
            ->orderByDesc('violation_count')
            ->limit($top)
            ->with('user:id,username,email,karma_points')
            ->get();

        if ($topOffenders->isEmpty()) {
            $this->warn('No user violations found.');
        } else {
            $this->table(
                ['User ID', 'Username', 'Karma', 'Violations', 'Actions'],
                $topOffenders->map(fn ($o) => [
                    $o->user_id,
                    $o->user?->username ?? 'Unknown',
                    $o->user?->karma_points ?? 0,
                    $o->violation_count,
                    $o->unique_actions,
                ])->toArray(),
            );
        }

        $this->newLine();

        // Top suspicious IPs
        $this->info("Top {$top} Suspicious IPs:");
        $suspiciousIps = RateLimitLog::select('ip_address')
            ->selectRaw('COUNT(*) as violation_count')
            ->selectRaw('COUNT(DISTINCT action) as unique_actions')
            ->selectRaw('COUNT(DISTINCT user_id) as unique_users')
            ->where('created_at', '>=', now()->subHours($hours))
            ->groupBy('ip_address')
            ->orderByDesc('violation_count')
            ->limit($top)
            ->get();

        if ($suspiciousIps->isEmpty()) {
            $this->warn('No IP violations found.');
        } else {
            $this->table(
                ['IP Address', 'Violations', 'Actions', 'Users'],
                $suspiciousIps->map(fn ($ip) => [
                    $ip->ip_address,
                    $ip->violation_count,
                    $ip->unique_actions,
                    $ip->unique_users,
                ])->toArray(),
            );
        }

        return self::SUCCESS;
    }
}
