<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class CalculateKarmaRanking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rankings:calculate-karma
                            {--timeframe=all : Timeframe to calculate (all, today, week, month)}
                            {--limit=100 : Number of users to cache}
                            {--force : Force recalculation even if cache exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and cache karma rankings';

    private const CACHE_TTL = 600; // 10 minutes

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timeframe = $this->option('timeframe');
        $limit = min((int) $this->option('limit'), 100);
        $force = $this->option('force');

        $this->info("Calculating karma ranking for timeframe: {$timeframe}");

        $cacheKey = "rankings:karma:{$timeframe}:{$limit}:1";

        // Check if cache exists and force flag is not set
        if (! $force && Cache::has($cacheKey)) {
            $this->warn('Cache already exists. Use --force to recalculate.');

            return self::SUCCESS;
        }

        // Calculate ranking
        $startTime = microtime(true);

        $query = User::select('id', 'username', 'display_name', 'avatar', 'avatar_url', 'karma_points', 'highest_level_id')
            ->with('currentLevel:id,name,badge')
            ->where('is_guest', false);

        // Apply timeframe filter using daily_karma_stats (much faster!)
        if ($timeframe !== 'all') {
            $dateFilter = $this->getDateFilter($timeframe);
            if ($dateFilter) {
                // Join with aggregated daily stats
                $query->leftJoin('daily_karma_stats', function ($join) use ($dateFilter): void {
                    $join->on('users.id', '=', 'daily_karma_stats.user_id')
                        ->where('daily_karma_stats.date', '>=', $dateFilter);
                })
                    ->select('users.id', 'users.username', 'users.display_name', 'users.avatar', 'users.avatar_url', 'users.karma_points', 'users.highest_level_id')
                    ->selectRaw('COALESCE(SUM(daily_karma_stats.karma_earned), 0) as period_karma')
                    ->groupBy('users.id', 'users.username', 'users.display_name', 'users.avatar', 'users.avatar_url', 'users.karma_points', 'users.highest_level_id')
                    ->orderBy('period_karma', 'desc');
            } else {
                $query->orderBy('karma_points', 'desc');
            }
        } else {
            $query->orderBy('karma_points', 'desc');
        }

        $users = $query->take($limit)->get();

        // Get total count for pagination
        $total = User::where('is_guest', false)->count();

        $data = [
            'users' => $users->map(fn ($user) => [
                'id' => $user->id,
                'username' => $user->username,
                'display_name' => $user->display_name,
                'avatar' => $user->avatar_url ?? $user->avatar,
                'karma_points' => $timeframe !== 'all' && isset($user->period_karma)
                    ? (int) $user->period_karma
                    : $user->karma_points,
                'level' => $user->currentLevel ? [
                    'id' => $user->currentLevel->id,
                    'name' => $user->currentLevel->name,
                    'badge' => $user->currentLevel->badge,
                ] : null,
            ]),
            'pagination' => [
                'current_page' => 1,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => (int) ceil($total / $limit),
            ],
        ];

        // Cache the results
        Cache::put($cacheKey, $data, self::CACHE_TTL);

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);

        $this->info("✓ Calculated ranking for {$users->count()} users in {$executionTime}s");
        $this->info("✓ Cached with key: {$cacheKey}");
        $this->info('✓ Cache TTL: ' . self::CACHE_TTL . ' seconds (' . (self::CACHE_TTL / 60) . ' minutes)');

        // Display top 10
        $this->newLine();
        $this->info('Top 10 users:');
        $this->table(
            ['Rank', 'Username', 'Display Name', 'Karma Points'],
            $users->take(10)->map(fn ($user, $index) => [
                $index + 1,
                $user->username,
                $user->display_name ?? '-',
                $timeframe !== 'all' && isset($user->period_karma)
                    ? $user->period_karma
                    : $user->karma_points,
            ]),
        );

        return self::SUCCESS;
    }

    /**
     * Get date filter based on timeframe.
     */
    private function getDateFilter(?string $timeframe): ?string
    {
        return match ($timeframe) {
            'today' => now()->startOfDay()->toDateTimeString(),
            'week' => now()->startOfWeek()->toDateTimeString(),
            'month' => now()->startOfMonth()->toDateTimeString(),
            default => null,
        };
    }
}
