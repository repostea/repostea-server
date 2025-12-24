<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CleanupUnverifiedUsers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'users:cleanup-unverified
                            {--days=30 : Number of days since registration to consider for cleanup}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Execute without confirmation}
                            {--with-activity : Include users with activity (will be anonymized)}';

    /**
     * The console command description.
     */
    protected $description = 'Cleanup unverified users (email not verified). Users without activity are soft-deleted, users with activity are anonymized.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $withActivity = $this->option('with-activity');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("🧹 Cleaning up unverified users created before: {$cutoffDate->format('Y-m-d H:i:s')}");
        $this->info("   (Users with email_verified_at = NULL and created more than {$days} days ago)");

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No users will be modified');
        }

        $this->newLine();

        // Get unverified users without activity
        $usersWithoutActivity = User::whereNull('email_verified_at')
            ->where('created_at', '<=', $cutoffDate)
            ->whereNull('deleted_at')
            ->where('is_deleted', false)
            ->whereDoesntHave('posts')
            ->whereDoesntHave('comments')
            ->whereDoesntHave('votes')
            ->get();

        // Get unverified users with activity (only if --with-activity flag is set)
        $usersWithActivity = collect();
        if ($withActivity) {
            $usersWithActivity = User::whereNull('email_verified_at')
                ->where('created_at', '<=', $cutoffDate)
                ->whereNull('deleted_at')
                ->where('is_deleted', false)
                ->where(function ($query): void {
                    $query->whereHas('posts')
                        ->orWhereHas('comments')
                        ->orWhereHas('votes');
                })
                ->get();
        }

        $totalUsers = $usersWithoutActivity->count() + $usersWithActivity->count();

        if ($totalUsers === 0) {
            $this->info('✅ No unverified users found to cleanup');

            return 0;
        }

        // Show summary
        $this->info('📊 Found users to cleanup:');
        $this->line("   Without activity (will be soft-deleted): {$usersWithoutActivity->count()}");
        if ($withActivity) {
            $this->line("   With activity (will be anonymized): {$usersWithActivity->count()}");
        }
        $this->line("   Total: {$totalUsers}");
        $this->newLine();

        // Ask for confirmation unless --force or --dry-run
        if (! $force && ! $dryRun) {
            if (! $this->confirm('Do you want to proceed with the cleanup?', false)) {
                $this->warn('Operation cancelled.');

                return 0;
            }
            $this->newLine();
        }

        $deletedCount = 0;
        $anonymizedCount = 0;
        $errors = 0;

        // Process users without activity (soft delete)
        if ($usersWithoutActivity->isNotEmpty()) {
            $this->info('🗑️  Processing users without activity (soft delete)...');
            $bar = $this->output->createProgressBar($usersWithoutActivity->count());
            $bar->start();

            foreach ($usersWithoutActivity as $user) {
                try {
                    $daysOld = (int) $user->created_at->diffInDays(now());
                    $originalUsername = $user->username;
                    $originalEmail = $user->email;

                    if (! $dryRun) {
                        DB::beginTransaction();

                        // Change email to free it for new registrations
                        $user->email = "deleted_{$user->id}_" . time() . '@deleted.local';
                        $user->save();

                        // Soft delete the user
                        $user->delete();

                        // Log to moderation log
                        $this->logToModerationLog($user, 'soft_delete', $originalEmail, $daysOld);

                        DB::commit();
                        $deletedCount++;

                        Log::info("Soft deleted unverified user without activity: {$originalUsername} (ID: {$user->id}) - {$daysOld} days old");
                    }
                } catch (Exception $e) {
                    if (! $dryRun) {
                        DB::rollBack();
                    }
                    $errors++;
                    Log::error("Error deleting user {$user->id}: {$e->getMessage()}");
                    $this->newLine();
                    $this->error("  Error processing user {$user->username}: {$e->getMessage()}");
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->newLine();
        }

        // Process users with activity (anonymize)
        if ($usersWithActivity->isNotEmpty()) {
            $this->info('🔒 Processing users with activity (anonymization)...');
            $bar = $this->output->createProgressBar($usersWithActivity->count());
            $bar->start();

            foreach ($usersWithActivity as $user) {
                try {
                    $daysOld = (int) $user->created_at->diffInDays(now());
                    $originalUsername = $user->username;
                    $originalEmail = $user->email;

                    if (! $dryRun) {
                        DB::beginTransaction();

                        // Get next deletion number
                        $deletionNumber = $this->getNextDeletionNumber();

                        // Anonymize user data
                        $user->email = "deleted_{$user->id}_" . time() . '@deleted.local';
                        $user->username = "deleted_user_{$deletionNumber}";
                        $user->is_deleted = true;
                        $user->deletion_number = $deletionNumber;
                        $user->deleted_at = now();

                        // Clear personal information
                        $user->display_name = null;
                        $user->bio = null;
                        $user->avatar_url = null;
                        $user->avatar = null;
                        $user->professional_title = null;
                        $user->institution = null;
                        $user->academic_degree = null;
                        $user->expertise_areas = null;

                        $user->save();

                        // Log to moderation log
                        $this->logToModerationLog($user, 'anonymize', $originalEmail, $daysOld);

                        DB::commit();
                        $anonymizedCount++;

                        Log::info("Anonymized unverified user with activity: {$originalUsername} (ID: {$user->id}) - {$daysOld} days old");
                    }
                } catch (Exception $e) {
                    if (! $dryRun) {
                        DB::rollBack();
                    }
                    $errors++;
                    Log::error("Error anonymizing user {$user->id}: {$e->getMessage()}");
                    $this->newLine();
                    $this->error("  Error processing user {$user->username}: {$e->getMessage()}");
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->newLine();
        }

        // Summary
        $this->info('📊 Cleanup Summary:');
        $this->newLine();

        if ($dryRun) {
            $this->line("  Would soft-delete: {$usersWithoutActivity->count()} users");
            if ($withActivity) {
                $this->line("  Would anonymize: {$usersWithActivity->count()} users");
            }
        } else {
            $this->line("  ✅ Soft-deleted: {$deletedCount} users");
            if ($withActivity) {
                $this->line("  ✅ Anonymized: {$anonymizedCount} users");
            }
            if ($errors > 0) {
                $this->line("  ❌ Errors: {$errors}");
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  This was a dry run. Run without --dry-run to actually cleanup users.');
        } else {
            $this->info('✅ Cleanup completed successfully!');
        }

        return 0;
    }

    /**
     * Get the next deletion number for anonymized users.
     */
    private function getNextDeletionNumber(): int
    {
        $maxDeletionNumber = User::withTrashed()
            ->whereNotNull('deletion_number')
            ->lockForUpdate()
            ->max('deletion_number');

        return ($maxDeletionNumber ?? 0) + 1;
    }

    /**
     * Log action to moderation log.
     */
    private function logToModerationLog(User $user, string $action, string $originalEmail, int $daysOld): void
    {
        try {
            // Check if moderation_logs table exists
            if (! DB::getSchemaBuilder()->hasTable('moderation_logs')) {
                return;
            }

            $actionText = $action === 'soft_delete'
                ? 'Soft-deleted unverified user without activity'
                : 'Anonymized unverified user with activity';

            DB::table('moderation_logs')->insert([
                'moderator_id' => null, // System action (automated)
                'target_user_id' => $user->id,
                'target_type' => 'User',
                'target_id' => $user->id,
                'action' => 'cleanup_unverified',
                'reason' => $actionText . " ({$daysOld} days old)",
                'metadata' => json_encode([
                    'action_type' => $action,
                    'original_email' => $originalEmail,
                    'days_old' => $daysOld,
                    'reason' => 'Automatic cleanup of unverified users',
                    'automated' => true,
                ]),
                'created_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::warning("Could not log to moderation_logs: {$e->getMessage()}");
        }
    }
}
