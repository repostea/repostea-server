<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

final class SyncProductionToStaging extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staging:sync-from-production
                            {--dry-run : Show what would be done without actually doing it}
                            {--skip-media : Skip copying media database}';

    /**
     * The console command description.
     */
    protected $description = 'Sync production database to staging and sanitize sensitive data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Safety check: only run in staging environment
        if (config('app.env') !== 'staging') {
            $this->error('❌ This command can only be run in staging environment!');
            $this->error('   Current environment: ' . config('app.env'));

            return 1;
        }

        $dryRun = $this->option('dry-run');
        $skipMedia = $this->option('skip-media');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $this->info('🚀 Starting production to staging sync...');
        $this->newLine();

        // Confirm before proceeding
        if (! $dryRun && ! $this->confirm('⚠️  This will REPLACE all data in staging databases. Continue?')) {
            $this->info('Aborted.');

            return 0;
        }

        $this->newLine();

        // Database names (hardcoded for safety)
        $prodDb = 'repostea';
        $stagingDb = config('database.connections.mysql.database');
        $prodMediaDb = 'repostea_media';
        $stagingMediaDb = config('database.connections.media.database');

        $dbUser = config('database.connections.mysql.username');
        $dbPassword = config('database.connections.mysql.password');

        // Step 1: Backup staging databases
        $this->info('💾 Step 1/4: Backing up staging databases...');
        $backupDir = storage_path('backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $timestamp = date('Y-m-d_H-i-s');
        $this->backupDatabase($stagingDb, "{$backupDir}/staging_backup_{$timestamp}.sql", $dbUser, $dbPassword, $dryRun);
        if (! $skipMedia) {
            $this->backupDatabase($stagingMediaDb, "{$backupDir}/staging_media_backup_{$timestamp}.sql", $dbUser, $dbPassword, $dryRun);
        }

        // Step 2: Copy main database
        $this->info('📦 Step 2/4: Copying main database...');
        $this->copyDatabase($prodDb, $stagingDb, $dbUser, $dbPassword, $dryRun);

        // Step 3: Copy media database
        if (! $skipMedia) {
            $this->info('📦 Step 3/4: Copying media database...');
            $this->copyDatabase($prodMediaDb, $stagingMediaDb, $dbUser, $dbPassword, $dryRun);
        } else {
            $this->warn('⏭️  Step 3/4: Skipping media database');
        }

        // Step 4: Sanitize emails
        $this->info('🔒 Step 4/4: Sanitizing user emails...');
        $this->sanitizeEmails($dryRun);

        $this->newLine();
        $this->info('✅ Sync completed successfully!');
        $this->newLine();

        if ($dryRun) {
            $this->warn('   This was a DRY RUN. Run without --dry-run to actually sync.');
        } else {
            $this->line('   All emails now have .test suffix');
            $this->line('   Passwords remain the same (from production)');
            $this->line('   You can login with your production credentials using: youremail@example.com.test');
        }

        return 0;
    }

    /**
     * Backup database to file.
     */
    private function backupDatabase(string $database, string $outputFile, string $user, string $password, bool $dryRun): void
    {
        if ($dryRun) {
            $this->line("   Would backup: {$database} → {$outputFile}");

            return;
        }

        $this->line("   Backing up: {$database}");

        // Create temporary MySQL config file for secure password handling
        // Password must be quoted to handle special characters like # . etc
        $tmpConfig = tempnam(sys_get_temp_dir(), 'my_cnf_');
        $escapedPassword = addslashes($password);
        file_put_contents($tmpConfig, "[client]\nuser={$user}\npassword=\"{$escapedPassword}\"\n");
        chmod($tmpConfig, 0600);

        // Use shell for output redirection, but with properly escaped arguments
        $process = Process::fromShellCommandline(
            'mysqldump --defaults-extra-file=' . escapeshellarg($tmpConfig) . ' ' . escapeshellarg($database) . ' > ' . escapeshellarg($outputFile),
        );
        $process->setTimeout(3600); // 1 hour for large databases
        $process->run();

        // Remove temp config file
        unlink($tmpConfig);

        if (! $process->isSuccessful()) {
            $this->error("   Failed to backup database: {$database}");
            $this->error('   ' . $process->getErrorOutput());

            exit(1);
        }

        $this->line("   ✓ Backup saved to: {$outputFile}");
    }

    /**
     * Copy database using mysqldump.
     */
    private function copyDatabase(string $source, string $destination, string $user, string $password, bool $dryRun): void
    {
        if ($dryRun) {
            $this->line("   Would copy: {$source} → {$destination}");

            return;
        }

        $this->line("   Copying: {$source} → {$destination}");

        // Create temporary MySQL config file for secure password handling
        // Password must be quoted to handle special characters like # . etc
        $tmpConfig = tempnam(sys_get_temp_dir(), 'my_cnf_');
        $escapedPassword = addslashes($password);
        file_put_contents($tmpConfig, "[client]\nuser={$user}\npassword=\"{$escapedPassword}\"\n");
        chmod($tmpConfig, 0600);

        // Use shell for pipe redirection, but with properly escaped arguments
        $process = Process::fromShellCommandline(
            'mysqldump --defaults-extra-file=' . escapeshellarg($tmpConfig) . ' ' . escapeshellarg($source) .
            ' | mysql --defaults-extra-file=' . escapeshellarg($tmpConfig) . ' ' . escapeshellarg($destination),
        );
        $process->setTimeout(3600); // 1 hour for large databases
        $process->run();

        // Remove temp config file
        unlink($tmpConfig);

        if (! $process->isSuccessful()) {
            $this->error("   Failed to copy database: {$source}");
            $this->error('   ' . $process->getErrorOutput());

            exit(1);
        }

        $this->line('   ✓ Copied successfully');
    }

    /**
     * Sanitize all user emails by adding .test suffix.
     */
    private function sanitizeEmails(bool $dryRun): void
    {
        if ($dryRun) {
            $count = DB::table('users')->count();
            $this->line("   Would sanitize {$count} user emails");

            return;
        }

        $updated = DB::table('users')
            ->whereNotLike('email', '%.test')
            ->update([
                'email' => DB::raw("CONCAT(email, '.test')"),
            ]);

        $this->line("   ✓ Sanitized {$updated} emails");
    }
}
