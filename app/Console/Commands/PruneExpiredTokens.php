<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneExpiredTokens extends Command
{
    protected $signature = 'tokens:prune {--days=30 : Days of inactivity before pruning}';

    protected $description = 'Delete personal access tokens that have not been used in the specified number of days';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $deleted = DB::table('personal_access_tokens')
            ->where(function ($query) use ($days): void {
                // Tokens used but not in the last X days
                $query->where('last_used_at', '<', now()->subDays($days))
                    // Or tokens never used and created more than X days ago
                    ->orWhere(function ($q) use ($days): void {
                        $q->whereNull('last_used_at')
                            ->where('created_at', '<', now()->subDays($days));
                    });
            })
            ->delete();

        $this->info("Pruned {$deleted} inactive tokens (not used in {$days} days).");

        return self::SUCCESS;
    }
}
