<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

final class RecalculateUserLevels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'karma:recalculate-levels {--user= : Specific user ID to recalculate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate karma levels for all users or a specific user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user');

        if ($userId) {
            $user = User::find($userId);
            if (! $user) {
                $this->error("User with ID {$userId} not found.");

                return 1;
            }

            $this->recalculateUserLevel($user);
            $this->info("Level recalculated for user: {$user->username}");

            return 0;
        }

        // Recalculate for all users
        $users = User::all();
        $this->info("Recalculating levels for {$users->count()} users...");

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        $updated = 0;
        foreach ($users as $user) {
            if ($this->recalculateUserLevel($user)) {
                $updated++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Level recalculation complete! Updated {$updated} users.");

        return 0;
    }

    /**
     * Recalculate level for a single user.
     *
     * @return bool Whether the level was updated
     */
    private function recalculateUserLevel(User $user): bool
    {
        $previousLevel = $user->highest_level_id;
        $currentLevel = $user->calculateCurrentLevel();

        // Only update if user reached a HIGHER level (levels are permanent unless admin intervention)
        if ($currentLevel && ($previousLevel === null || $currentLevel->id > $previousLevel)) {
            $user->highest_level_id = $currentLevel->id;
            $user->save();

            return true;
        }

        return false;
    }
}
