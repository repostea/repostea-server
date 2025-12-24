<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class MbinSyncPasswords extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mbin:sync-passwords
                            {--dry-run : Show what would be updated without making changes}
                            {--username= : Sync only specific username}';

    /**
     * The console command description.
     */
    protected $description = 'Synchronize user passwords from Mbin to Repostea to ensure users have the same password in both systems';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $specificUsername = $this->option('username');

        $this->info('🔐 Starting password synchronization from Mbin to Repostea...');
        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        try {
            // Test Mbin connection
            if (! $this->testMbinConnection()) {
                $this->error('❌ Could not connect to Mbin database. Check your database configuration.');

                return 1;
            }

            // Get users to sync
            $query = DB::connection('mbin')->table('user as mu')
                ->select('mu.id', 'mu.username', 'mu.email', 'mu.password');

            if ($specificUsername) {
                $query->where('mu.username', $specificUsername);
            }

            $mbinUsers = $query->get();

            if ($mbinUsers->isEmpty()) {
                $this->warn('No users found in Mbin to sync.');

                return 0;
            }

            $this->info("Found {$mbinUsers->count()} users in Mbin to process");
            $this->newLine();

            $bar = $this->output->createProgressBar($mbinUsers->count());
            $bar->start();

            $updated = 0;
            $notFound = 0;
            $unchanged = 0;
            $errors = 0;

            foreach ($mbinUsers as $mbinUser) {
                try {
                    // Find user in Repostea by username or email
                    $reposteaUser = DB::table('users')
                        ->where('username', $mbinUser->username)
                        ->orWhere('email', $mbinUser->email)
                        ->first();

                    if (! $reposteaUser) {
                        $notFound++;
                        Log::info("User {$mbinUser->username} not found in Repostea");
                        $bar->advance();

                        continue;
                    }

                    // Check if password needs update
                    if ($reposteaUser->password === $mbinUser->password) {
                        $unchanged++;
                        $bar->advance();

                        continue;
                    }

                    // Update password
                    if (! $dryRun) {
                        DB::table('users')
                            ->where('id', $reposteaUser->id)
                            ->update([
                                'password' => $mbinUser->password,
                                'updated_at' => now(),
                            ]);

                        Log::info("Password synced for user: {$mbinUser->username} (Repostea ID: {$reposteaUser->id})");
                    } else {
                        Log::info("[DRY RUN] Would sync password for user: {$mbinUser->username} (Repostea ID: {$reposteaUser->id})");
                    }

                    $updated++;

                } catch (Exception $e) {
                    $errors++;
                    Log::error("Error syncing password for user {$mbinUser->username}: {$e->getMessage()}");
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->newLine();

            // Show summary
            $this->info('📊 Synchronization Summary:');
            $this->newLine();
            $this->line("  ✅ Passwords updated: {$updated}");
            $this->line("  ⏭️  Unchanged (already synced): {$unchanged}");
            $this->line("  ❓ Users not found in Repostea: {$notFound}");

            if ($errors > 0) {
                $this->line("  ❌ Errors: {$errors}");
            }

            $this->newLine();

            if ($dryRun && $updated > 0) {
                $this->warn('⚠️  This was a dry run. Run without --dry-run to apply changes.');
            } elseif ($updated > 0) {
                $this->info('✅ Password synchronization completed successfully!');
            } else {
                $this->info('✅ All passwords are already in sync!');
            }

            Log::info('Password sync completed', [
                'updated' => $updated,
                'unchanged' => $unchanged,
                'not_found' => $notFound,
                'errors' => $errors,
                'dry_run' => $dryRun,
            ]);

            return 0;

        } catch (Exception $e) {
            $this->error("Error during password synchronization: {$e->getMessage()}");
            Log::error("Error in mbin:sync-passwords: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Test connection to Mbin database.
     */
    private function testMbinConnection(): bool
    {
        try {
            DB::connection('mbin')->getPdo();

            return true;
        } catch (Exception $e) {
            Log::error("Error connecting to Mbin: {$e->getMessage()}");

            return false;
        }
    }
}
