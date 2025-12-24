<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SealService;
use Exception;
use Illuminate\Console\Command;

final class AwardWeeklySeals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seals:award-weekly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Award weekly seals to users based on their karma level';

    protected SealService $sealService;

    public function __construct(SealService $sealService)
    {
        parent::__construct();
        $this->sealService = $sealService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting weekly seals award process...');

        // Get all active users with karma level 2 or higher
        // Level 1 (Novato) gets 0 seals, so we skip them
        $users = User::active()
            ->approved()
            ->whereNotNull('highest_level_id')
            ->where('highest_level_id', '>=', 2)
            ->get();

        $totalAwarded = 0;
        $usersProcessed = 0;

        foreach ($users as $user) {
            try {
                $sealsAwarded = $this->sealService->awardWeeklySeals($user);

                if ($sealsAwarded > 0) {
                    $totalAwarded += $sealsAwarded;
                    $usersProcessed++;

                    $this->line("Awarded {$sealsAwarded} seals to {$user->username} (Level {$user->highest_level_id})");
                }
            } catch (Exception $e) {
                $this->error("Error awarding seals to user {$user->id}: " . $e->getMessage());
            }
        }

        $this->info('✓ Weekly seals awarded successfully!');
        $this->info("✓ Processed {$usersProcessed} users");
        $this->info("✓ Total seals awarded: {$totalAwarded}");

        // Cleanup expired seal marks
        $this->info('Cleaning up expired seal marks...');
        $expiredMarks = $this->sealService->cleanupExpiredMarks();
        $this->info("✓ Removed {$expiredMarks} expired seal marks");

        return Command::SUCCESS;
    }
}
