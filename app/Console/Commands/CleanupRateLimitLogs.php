<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RateLimitLog;
use Illuminate\Console\Command;

final class CleanupRateLimitLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rate-limit:cleanup
                            {--days= : Number of days to retain logs (default from config)}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old rate limit logs based on retention policy';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $retentionDays = $this->option('days')
            ? (int) $this->option('days')
            : config('rate_limits.monitoring.log_retention_days', 30);

        $cutoffDate = now()->subDays($retentionDays);

        // Show what will be deleted
        $count = RateLimitLog::where('created_at', '<', $cutoffDate)->count();

        if ($count === 0) {
            $this->info('No old logs to cleanup.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} logs older than {$retentionDays} days (before {$cutoffDate->toDateString()})");

        if (! $this->option('force')) {
            if (! $this->confirm('Do you want to delete these logs?', true)) {
                $this->info('Cleanup cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info('Deleting old logs...');

        $deleted = RateLimitLog::where('created_at', '<', $cutoffDate)->delete();

        $this->info("Successfully deleted {$deleted} old rate limit logs.");

        return self::SUCCESS;
    }
}
