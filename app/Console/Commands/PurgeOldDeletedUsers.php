<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class PurgeOldDeletedUsers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'users:purge-deleted
                            {--days=15 : Number of days after which to purge deleted users}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Anonymize users that have been soft-deleted for more than the specified days (default: 15 days)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("🔒 Anonymizing users deleted before: {$cutoffDate->format('Y-m-d H:i:s')}");

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No users will be anonymized');
        }

        $this->newLine();

        // Get users to be anonymized
        $users = User::onlyTrashed()
            ->where('deleted_at', '<=', $cutoffDate)
            ->get();

        if ($users->isEmpty()) {
            $this->info('✅ No users found to anonymize');

            return 0;
        }

        $this->info("Found {$users->count()} users to anonymize:");
        $this->newLine();

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        $anonymized = 0;
        $errors = 0;

        foreach ($users as $user) {
            try {
                $deletedDaysAgo = $user->deleted_at->diffInDays(now());
                $originalUsername = $user->username;
                $originalEmail = $user->email;

                if (! $dryRun) {
                    DB::beginTransaction();

                    // Generate anonymous username and email
                    $anonymousUsername = 'deleted_user_' . $user->id . '_' . time();
                    $anonymousEmail = 'deleted_' . $user->id . '_' . time() . '@deleted.local';

                    // Update user with anonymous data
                    $user->username = $anonymousUsername;
                    $user->email = $anonymousEmail;
                    $user->save();

                    // Log the anonymization
                    Log::info("Anonymized user {$originalUsername} (ID: {$user->id}) - deleted {$deletedDaysAgo} days ago");

                    // Permanently delete the soft-deleted record
                    $user->forceDelete();

                    DB::commit();
                    $anonymized++;
                } else {
                    $this->newLine();
                    $this->line("  Would anonymize: {$user->username} (ID: {$user->id}) - deleted {$deletedDaysAgo} days ago");
                }
            } catch (Exception $e) {
                if (! $dryRun) {
                    DB::rollBack();
                }
                $errors++;
                Log::error("Error anonymizing user {$user->id}: {$e->getMessage()}");
                $this->newLine();
                $this->error("  Error anonymizing user {$user->username}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->newLine();

        // Summary
        $this->info('📊 Anonymization Summary:');
        $this->newLine();

        if ($dryRun) {
            $this->line("  Would anonymize: {$users->count()} users");
        } else {
            $this->line("  ✅ Anonymized: {$anonymized} users");
            if ($errors > 0) {
                $this->line("  ❌ Errors: {$errors}");
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  This was a dry run. Run without --dry-run to actually anonymize users.');
        } else {
            $this->info('✅ Anonymization completed successfully!');
        }

        return 0;
    }
}
