<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DailyKarmaStat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class UpdateTodayKarmaStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'karma:update-today
                            {--date= : Specific date to update (YYYY-MM-DD), default: today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update karma stats for today (or specified date) - runs every 5 minutes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->option('date') ?? now()->toDateString();
        $startTime = microtime(true);

        $this->info("Updating karma stats for {$date}...");

        // Aggregate karma for the specified date
        $stats = DB::table('karma_histories')
            ->select(
                'user_id',
                DB::raw('SUM(amount) as karma_earned'),
            )
            ->whereDate('created_at', $date)
            ->groupBy('user_id')
            ->get();

        if ($stats->isEmpty()) {
            $this->info('No karma activity for this date.');

            return self::SUCCESS;
        }

        $updated = 0;
        $created = 0;

        foreach ($stats as $stat) {
            $record = DailyKarmaStat::updateOrCreate(
                [
                    'user_id' => $stat->user_id,
                    'date' => $date,
                ],
                [
                    'karma_earned' => $stat->karma_earned,
                ],
            );

            if ($record->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);

        $this->info("✓ Completed in {$executionTime}s");
        $this->info("  Created: {$created} records");
        $this->info("  Updated: {$updated} records");

        return self::SUCCESS;
    }
}
