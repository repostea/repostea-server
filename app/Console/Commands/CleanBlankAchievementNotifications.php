<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class CleanBlankAchievementNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'achievements:clean-blank-notifications
                            {--dry-run : Show what would be deleted without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete blank achievement notifications created by rebuild-history command';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->info('Looking for blank achievement notifications...');
        $this->newLine();

        // Find notifications with type 'achievement_unlocked' that have incomplete data
        // These are the ones created directly by RebuildAchievementsHistory before the fix
        $query = DB::table('notifications')
            ->where('type', 'achievement_unlocked')
            ->where(function ($q): void {
                // Check if data contains achievement_name or achievement_slug
                // Old format from rebuild: has achievement_name/description fields
                // New format: uses proper notification class with title/body
                $q->whereRaw("JSON_EXTRACT(data, '$.achievement_name') IS NOT NULL")
                    ->whereRaw("JSON_EXTRACT(data, '$.title') IS NULL");
            });

        $count = $query->count();

        if ($count === 0) {
            $this->info('No blank notifications found. Everything looks good!');

            return self::SUCCESS;
        }

        $this->warn("Found {$count} blank/old format notifications to delete");
        $this->newLine();

        if ($dryRun) {
            // Show sample
            $sample = $query->limit(3)->get();
            $this->line('Sample notifications that would be deleted:');
            foreach ($sample as $notification) {
                $data = json_decode($notification->data, true);
                $this->line("  - ID: {$notification->id}, User: {$notification->notifiable_id}, Data: " . substr($notification->data, 0, 100));
            }
            $this->newLine();
            $this->warn('This was a dry run. Run without --dry-run to delete these notifications.');
        } else {
            if (! $this->confirm("Are you sure you want to delete {$count} notifications?", true)) {
                $this->info('Aborted.');

                return self::FAILURE;
            }

            $deleted = $query->delete();
            $this->info("✓ Deleted {$deleted} notifications successfully!");
        }

        return self::SUCCESS;
    }
}
