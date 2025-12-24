<?php

declare(strict_types=1);

namespace App\Console\Commands;

use const PATHINFO_EXTENSION;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

final class ImportDatabaseFromFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staging:import-from-file
                            {--source= : Path to main database .sql or .zip file}
                            {--media-source= : Path to media database .sql or .zip file}
                            {--dry-run : Show what would be done without actually doing it}
                            {--skip-media : Skip importing media database}
                            {--skip-sanitize : Skip sanitizing emails}';

    /**
     * The console command description.
     */
    protected $description = 'Import database from .sql or .zip backup files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Safety check: only run in staging or local environment
        $env = config('app.env');
        if (! in_array($env, ['staging', 'local'], true)) {
            $this->error('❌ This command can only be run in staging or local environment!');
            $this->error('   Current environment: ' . $env);

            return 1;
        }

        $dryRun = $this->option('dry-run');
        $skipMedia = $this->option('skip-media');
        $skipSanitize = $this->option('skip-sanitize');
        $sourceFile = $this->option('source');
        $mediaSourceFile = $this->option('media-source');

        // Validate source file
        if (empty($sourceFile)) {
            $this->error('❌ --source option is required');
            $this->line('   Examples:');
            $this->line('     --source=/path/to/backup_2025-01-01.sql');
            $this->line('     --source=~/backups/backup_2025-01-01.zip');

            return 1;
        }

        if (! $skipMedia && empty($mediaSourceFile)) {
            $this->error('❌ --media-source option is required (or use --skip-media)');
            $this->line('   Examples:');
            $this->line('     --media-source=/path/to/backup_media_2025-01-01.sql');
            $this->line('     --media-source=~/backups/backup_media_2025-01-01.zip');

            return 1;
        }

        // Expand ~ to home directory
        $sourceFile = $this->expandPath($sourceFile);
        if ($mediaSourceFile !== null && $mediaSourceFile !== '') {
            $mediaSourceFile = $this->expandPath($mediaSourceFile);
        }

        // Validate files exist
        if (! file_exists($sourceFile)) {
            $this->error("❌ Source file not found: {$sourceFile}");

            return 1;
        }

        if (! $skipMedia && ! file_exists($mediaSourceFile)) {
            $this->error("❌ Media source file not found: {$mediaSourceFile}");

            return 1;
        }

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $this->info('🚀 Starting database import from files...');
        $this->newLine();

        // Confirm before proceeding
        $targetEnv = $env === 'local' ? 'local' : 'staging';
        if (! $dryRun && ! $this->confirm("⚠️  This will REPLACE all data in {$targetEnv} databases. Continue?")) {
            $this->info('Aborted.');

            return 0;
        }

        $this->newLine();

        // Get database credentials
        $targetDb = config('database.connections.mysql.database');
        $targetMediaDb = config('database.connections.media.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPassword = config('database.connections.mysql.password');

        // Step 1: Import main database
        $this->info('📦 Step 1/3: Importing main database...');
        $this->importFromFile($sourceFile, $targetDb, $dbUser, $dbPassword, $dryRun);

        // Step 2: Import media database
        if (! $skipMedia) {
            $this->info('📦 Step 2/3: Importing media database...');
            $this->importFromFile($mediaSourceFile, $targetMediaDb, $dbUser, $dbPassword, $dryRun);
        } else {
            $this->warn('⏭️  Step 2/3: Skipping media database');
        }

        // Step 3: Sanitize emails
        if (! $skipSanitize) {
            $this->info('🔒 Step 3/3: Sanitizing user emails...');
            $this->sanitizeEmails($dryRun);
        } else {
            $this->warn('⏭️  Step 3/3: Skipping email sanitization');
        }

        $this->newLine();
        $this->info('✅ Import completed successfully!');
        $this->newLine();

        if ($dryRun) {
            $this->warn('   This was a DRY RUN. Run without --dry-run to actually import.');
        } elseif (! $skipSanitize) {
            $this->line('   All emails now have .test suffix');
            $this->line('   Passwords remain the same (from backup)');
            $this->line('   You can login with your credentials using: youremail@example.com.test');
        }

        return 0;
    }

    /**
     * Expand ~ to home directory.
     */
    private function expandPath(string $path): string
    {
        if (str_starts_with($path, '~')) {
            return str_replace('~', getenv('HOME'), $path);
        }

        return $path;
    }

    /**
     * Import database from .sql or .zip file.
     */
    private function importFromFile(string $filePath, string $destination, string $user, string $password, bool $dryRun): void
    {
        if ($dryRun) {
            $this->line("   Would import: {$filePath} → {$destination}");

            return;
        }

        $this->line("   Importing from: {$filePath}");

        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

        if ($fileExtension === 'zip') {
            $this->importFromZip($filePath, $destination, $user, $password);
        } elseif ($fileExtension === 'sql') {
            $this->importSqlFile($filePath, $destination, $user, $password);
        } else {
            $this->error("   Unsupported file format: {$fileExtension}");
            $this->error('   Supported formats: .sql, .zip');
            exit(1);
        }

        $this->line('   ✓ Imported successfully');
    }

    /**
     * Import from .zip file.
     */
    private function importFromZip(string $zipPath, string $destination, string $user, string $password): void
    {
        $this->line('   Extracting ZIP file...');
        $tempDir = sys_get_temp_dir() . '/db_import_' . time();
        mkdir($tempDir);

        $process = new Process(['unzip', '-q', $zipPath, '-d', $tempDir]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('   Failed to extract ZIP file');
            exit(1);
        }

        // Find the .sql file in the extracted contents
        $sqlFiles = glob($tempDir . '/*.sql');
        if (empty($sqlFiles)) {
            $this->error('   No .sql file found in ZIP archive');
            $this->removeDirectory($tempDir);
            exit(1);
        }

        $sqlFile = $sqlFiles[0];
        $this->line('   Found SQL file: ' . basename($sqlFile));
        $this->importSqlFile($sqlFile, $destination, $user, $password);

        // Cleanup
        $this->removeDirectory($tempDir);
    }

    /**
     * Remove directory recursively using PHP.
     */
    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Import .sql file into database.
     */
    private function importSqlFile(string $sqlFile, string $destination, string $user, string $password): void
    {
        // Create temporary MySQL config file for secure password handling
        // Password must be quoted to handle special characters like # . etc
        $tmpConfig = tempnam(sys_get_temp_dir(), 'my_cnf_');
        $escapedPassword = addslashes($password);
        file_put_contents($tmpConfig, "[client]\nuser={$user}\npassword=\"{$escapedPassword}\"\n");
        chmod($tmpConfig, 0600);

        // Use shell for input redirection, but with properly escaped arguments
        $process = Process::fromShellCommandline(
            'mysql --defaults-extra-file=' . escapeshellarg($tmpConfig) . ' ' . escapeshellarg($destination) . ' < ' . escapeshellarg($sqlFile),
        );
        $process->setTimeout(3600); // 1 hour for large imports
        $process->run();

        // Remove temp config file
        unlink($tmpConfig);

        if (! $process->isSuccessful()) {
            $this->error('   Failed to import SQL file');
            $this->error('   ' . $process->getErrorOutput());
            exit(1);
        }
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
