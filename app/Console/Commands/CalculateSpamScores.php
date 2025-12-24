<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DuplicateContentDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class CalculateSpamScores extends Command
{
    protected $signature = 'spam:calculate-scores
                          {--hours=24 : Hours of activity to check}
                          {--min-activity=1 : Minimum posts/comments to check user}';

    protected $description = 'Calculate spam scores for active users and cache results';

    public function handle(DuplicateContentDetector $detector): int
    {
        $hours = (int) $this->option('hours');
        $minActivity = (int) $this->option('min-activity');

        $this->info("Calculating spam scores for users active in last {$hours} hours...");

        // Get users with recent activity
        $activeUsers = User::where('created_at', '>=', now()->subHours($hours))
            ->orWhereHas('posts', function ($query) use ($hours): void {
                $query->where('created_at', '>=', now()->subHours($hours));
            })
            ->orWhereHas('comments', function ($query) use ($hours): void {
                $query->where('created_at', '>=', now()->subHours($hours));
            })
            ->get();

        $this->info("Found {$activeUsers->count()} active users");

        $processedCount = 0;
        $suspiciousCount = 0;
        $processedUserIds = [];

        $bar = $this->output->createProgressBar($activeUsers->count());

        foreach ($activeUsers as $user) {
            // Skip users with very low activity
            $activityCount = $user->posts()->where('created_at', '>=', now()->subHours($hours))->count()
                           + $user->comments()->where('created_at', '>=', now()->subHours($hours))->count();

            if ($activityCount < $minActivity) {
                $bar->advance();

                continue;
            }

            $spamScore = $detector->getSpamScore($user->id);

            // Cache score for 15 minutes
            Cache::put(
                "spam_score:{$user->id}",
                $spamScore,
                now()->addMinutes(15),
            );

            $processedUserIds[] = $user->id;
            $processedCount++;

            if ($spamScore['is_spam']) {
                $suspiciousCount++;
                $this->newLine();
                $this->warn("⚠️  User #{$user->id} ({$user->username}) - Score: {$spamScore['score']} ({$spamScore['risk_level']})");
                $this->line('   Reasons: ' . implode(', ', $spamScore['reasons']));
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Store list of processed user IDs for dashboard
        Cache::put('spam_score_users', $processedUserIds, now()->addMinutes(20));
        Cache::put('spam_score_last_scan', now(), now()->addHours(1));

        $this->info("✅ Processed {$processedCount} users");
        $this->info("⚠️  Found {$suspiciousCount} suspicious users");

        return self::SUCCESS;
    }
}
