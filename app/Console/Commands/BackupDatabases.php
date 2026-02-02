<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

final class BackupDatabases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:databases
                            {--skip-media : Skip backing up the media database}
                            {--retention=7 : Number of days to keep old backups}
                            {--no-cleanup : Skip cleanup of old backups}';

    /**
     * The console command description.
     */
    protected $description = 'Create backups of main and media databases';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting database backup...');
        $this->newLine();

        $backupDir = storage_path('app/backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $skipMedia = $this->option('skip-media');

        // Get database credentials
        $dbUser = config('database.connections.mysql.username');
        $dbPassword = config('database.connections.mysql.password');
        $mainDatabase = config('database.connections.mysql.database');
        $mediaDatabase = config('database.connections.media.database');

        // Backup main database
        $mainBackupFile = "{$backupDir}/backup_main_{$timestamp}.sql.gz";
        $this->info("Backing up main database: {$mainDatabase}");

        if (! $this->backupDatabase($mainDatabase, $mainBackupFile, $dbUser, $dbPassword)) {
            $this->error('Failed to backup main database');

            return 1;
        }

        $this->info("Main database backed up to: {$mainBackupFile}");

        // Backup media database
        if (! $skipMedia && $mediaDatabase) {
            $mediaBackupFile = "{$backupDir}/backup_media_{$timestamp}.sql.gz";
            $this->info("Backing up media database: {$mediaDatabase}");

            if (! $this->backupDatabase($mediaDatabase, $mediaBackupFile, $dbUser, $dbPassword)) {
                $this->error('Failed to backup media database');

                return 1;
            }

            $this->info("Media database backed up to: {$mediaBackupFile}");
        }

        // Cleanup old backups
        if (! $this->option('no-cleanup')) {
            $retention = (int) $this->option('retention');
            $this->cleanupOldBackups($backupDir, $retention);
        }

        $this->newLine();
        $this->info('Database backup completed successfully!');

        return 0;
    }

    /**
     * Backup a single database to a gzipped file.
     */
    private function backupDatabase(string $database, string $outputFile, string $user, string $password): bool
    {
        // Create temporary MySQL config file for secure password handling
        // Password must be quoted to handle special characters like # . etc
        $tmpConfig = tempnam(sys_get_temp_dir(), 'my_cnf_');
        if ($tmpConfig === false) {
            $this->error('Failed to create temporary config file');

            return false;
        }

        $escapedPassword = addslashes($password);
        file_put_contents($tmpConfig, "[client]\nuser={$user}\npassword=\"{$escapedPassword}\"\n");
        chmod($tmpConfig, 0600);

        // Use mysqldump with gzip compression
        $process = Process::fromShellCommandline(
            'mysqldump --defaults-extra-file=' . escapeshellarg($tmpConfig) . ' ' .
            escapeshellarg($database) . ' | gzip > ' . escapeshellarg($outputFile),
        );
        $process->setTimeout(3600); // 1 hour timeout for large databases
        $process->run();

        // Remove temp config file
        unlink($tmpConfig);

        if (! $process->isSuccessful()) {
            $this->error('mysqldump error: ' . $process->getErrorOutput());

            // Remove empty/failed backup file
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }

            return false;
        }

        // Verify the backup file is not empty
        if (! file_exists($outputFile) || filesize($outputFile) === 0) {
            $this->error('Backup file is empty or does not exist');

            if (file_exists($outputFile)) {
                unlink($outputFile);
            }

            return false;
        }

        $sizeMb = round(filesize($outputFile) / 1024 / 1024, 2);
        $this->line("   Size: {$sizeMb} MB");

        return true;
    }

    /**
     * Remove backup files older than the retention period.
     */
    private function cleanupOldBackups(string $backupDir, int $retentionDays): void
    {
        $this->info("Cleaning up backups older than {$retentionDays} days...");

        $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);
        $deletedCount = 0;

        $files = glob("{$backupDir}/backup_*.sql.gz");
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $deletedCount++;
                $this->line('   Deleted: ' . basename($file));
            }
        }

        if ($deletedCount === 0) {
            $this->line('   No old backups to delete');
        } else {
            $this->line("   Deleted {$deletedCount} old backup(s)");
        }
    }
}
