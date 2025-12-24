<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RecalculateAllKarma extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'karma:recalculate-all
                            {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate karma for all users based on karma_histories table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->info('Recalculating karma for all users...');
        $this->newLine();

        // Get all users with their karma from karma_histories
        $usersWithKarma = DB::table('karma_histories')
            ->select('user_id', DB::raw('SUM(amount) as total_karma'))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        // Get all users
        $users = User::whereNotNull('id')->get();

        $updated = 0;
        $unchanged = 0;
        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            $calculatedKarma = $usersWithKarma->get($user->id)?->total_karma ?? 0;
            $currentKarma = $user->karma_points;

            if ($calculatedKarma !== $currentKarma) {
                if (! $dryRun) {
                    // Update karma points
                    $user->karma_points = $calculatedKarma;

                    // Recalculate and update level if needed (same logic as updateKarma)
                    $currentLevel = $user->calculateCurrentLevel();
                    if ($currentLevel && ($user->highest_level_id === null || $currentLevel->id > $user->highest_level_id)) {
                        $user->highest_level_id = $currentLevel->id;
                    }

                    $user->save();
                }
                $updated++;

                if ($this->output->isVerbose()) {
                    $this->newLine();
                    $this->line("  User {$user->username}: {$currentKarma} → {$calculatedKarma}");
                }
            } else {
                $unchanged++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Statistics
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total users processed', $users->count()],
                ['Users updated', $updated],
                ['Users unchanged', $unchanged],
                ['Mode', $dryRun ? 'DRY RUN' : 'LIVE'],
            ],
        );

        if ($dryRun) {
            $this->warn('This was a dry run. Use without --dry-run to apply changes.');
        } else {
            $this->info('✓ Karma recalculation completed successfully!');
            $this->newLine();
            $this->comment('Next steps:');
            $this->line('  1. Run: php artisan karma:populate-daily-stats --truncate --from=2024-01-01');
            $this->line('  2. Run: php artisan cache:clear');
        }

        return self::SUCCESS;
    }
}
