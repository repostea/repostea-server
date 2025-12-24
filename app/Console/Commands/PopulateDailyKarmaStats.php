<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DailyKarmaStat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PopulateDailyKarmaStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'karma:populate-daily-stats
                            {--from= : Start date (YYYY-MM-DD), default: beginning of karma_histories}
                            {--to= : End date (YYYY-MM-DD), default: today}
                            {--truncate : Truncate table before populating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate daily karma stats from karma_histories (one-time or historical)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $truncate = $this->option('truncate');
        $from = $this->option('from');
        $to = $this->option('to') ?? now()->toDateString();

        if ($truncate) {
            $this->warn('Truncating daily_karma_stats table...');
            DailyKarmaStat::truncate();
        }

        // Get date range from karma_histories if not specified
        if (! $from) {
            $firstRecord = DB::table('karma_histories')
                ->orderBy('created_at', 'asc')
                ->first();

            if (! $firstRecord) {
                $this->error('No karma_histories records found!');

                return self::FAILURE;
            }

            $from = date('Y-m-d', strtotime($firstRecord->created_at));
        }

        $this->info("Populating daily karma stats from {$from} to {$to}...");
        $startTime = microtime(true);

        // Aggregate karma by user and date
        $stats = DB::table('karma_histories')
            ->select(
                'user_id',
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as karma_earned'),
            )
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->groupBy('user_id', DB::raw('DATE(created_at)'))
            ->get();

        if ($stats->isEmpty()) {
            $this->warn('No karma history found in the specified date range.');

            return self::SUCCESS;
        }

        $this->info("Found {$stats->count()} daily records to insert...");

        // Insert in batches for performance
        $bar = $this->output->createProgressBar($stats->count());
        $bar->start();

        foreach ($stats->chunk(100) as $chunk) {
            $data = $chunk->map(fn ($stat) => [
                'user_id' => $stat->user_id,
                'date' => $stat->date,
                'karma_earned' => $stat->karma_earned,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

            DailyKarmaStat::insertOrIgnore($data);
            $bar->advance($chunk->count());
        }

        $bar->finish();
        $this->newLine(2);

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);

        $totalRecords = DailyKarmaStat::count();
        $this->info("✓ Populated {$totalRecords} daily karma records in {$executionTime}s");

        // Show some statistics
        $this->newLine();
        $this->info('Statistics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total days tracked', DailyKarmaStat::distinct('date')->count()],
                ['Total users with karma', DailyKarmaStat::distinct('user_id')->count()],
                ['Date range', DailyKarmaStat::min('date') . ' to ' . DailyKarmaStat::max('date')],
            ],
        );

        return self::SUCCESS;
    }
}
