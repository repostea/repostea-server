<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\ErrorHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use ZipArchive;

final class AdminController extends Controller
{
    /**
     * Get database statistics including size and largest tables.
     */
    public function getDatabaseStats(): JsonResponse
    {
        $mainConnection = config('database.default');
        $mediaConnection = 'media';

        $stats = [
            'main_database' => $this->getDatabaseSize($mainConnection),
            'media_database' => $this->getDatabaseSize($mediaConnection),
            'largest_tables' => $this->getLargestTables($mainConnection, 3),
        ];

        return ApiResponse::success($stats);
    }

    /**
     * Create a database backup and return it directly.
     */
    public function createBackup()
    {
        try {
            $timestamp = now()->format('Y-m-d_H-i-s');
            $backupPath = storage_path('app/backups');

            // Create backups directory if it doesn't exist
            if (! file_exists($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            $mainBackupFile = "{$backupPath}/backup_main_{$timestamp}.sql";
            $mediaBackupFile = "{$backupPath}/backup_media_{$timestamp}.sql";
            $zipFile = "{$backupPath}/backup_{$timestamp}.zip";

            // Backup main database
            $this->backupDatabase(config('database.default'), $mainBackupFile);

            // Backup media database
            $this->backupDatabase('media', $mediaBackupFile);

            // Create zip file with both backups
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
                throw new Exception('Could not create zip file');
            }

            $zip->addFile($mainBackupFile, basename($mainBackupFile));
            $zip->addFile($mediaBackupFile, basename($mediaBackupFile));
            $zip->close();

            // Clean up individual SQL files
            unlink($mainBackupFile);
            unlink($mediaBackupFile);

            // Return file download directly and delete after
            return response()->download($zipFile, basename($zipFile))->deleteFileAfterSend(true);

        } catch (Exception $e) {
            return ApiResponse::error(
                __('messages.admin.backup_failed'),
                ErrorHelper::getSafeError($e),
                500,
            );
        }
    }

    /**
     * Create backup of a single database (main or media) and return it directly as ZIP.
     */
    public function createSingleBackup(string $database)
    {
        try {
            if (! in_array($database, ['main', 'media'])) {
                return ApiResponse::error(__('messages.admin.invalid_database'), null, 400);
            }

            $timestamp = now()->format('Y-m-d_H-i-s');
            $backupPath = storage_path('app/backups');

            // Create backups directory if it doesn't exist
            if (! file_exists($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            $connection = $database === 'main' ? config('database.default') : 'media';
            $backupFile = "{$backupPath}/backup_{$database}_{$timestamp}.sql";
            $zipFile = "{$backupPath}/backup_{$database}_{$timestamp}.zip";

            // Backup the database
            $this->backupDatabase($connection, $backupFile);

            // Create zip file
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
                throw new Exception('Could not create zip file');
            }

            $zip->addFile($backupFile, basename($backupFile));
            $zip->close();

            // Clean up SQL file
            unlink($backupFile);

            // Return ZIP file download and delete after
            return response()->download($zipFile, basename($zipFile))->deleteFileAfterSend(true);

        } catch (Exception $e) {
            return ApiResponse::error(
                __('messages.admin.backup_failed'),
                ErrorHelper::getSafeError($e),
                500,
            );
        }
    }

    /**
     * Get the size of a database.
     */
    private function getDatabaseSize(string $connection): array
    {
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");

        try {
            if ($driver === 'mysql') {
                $result = DB::connection($connection)
                    ->select('SELECT
                        table_schema AS "database_name",
                        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS "size_mb"
                    FROM information_schema.TABLES
                    WHERE table_schema = ?
                    GROUP BY table_schema', [$database]);

                return [
                    'name' => $database,
                    'size_mb' => $result[0]->size_mb ?? 0,
                    'connection' => $connection,
                ];
            } elseif ($driver === 'sqlite') {
                $size = file_exists($database) ? filesize($database) : 0;

                return [
                    'name' => basename($database),
                    'size_mb' => round($size / 1024 / 1024, 2),
                    'connection' => $connection,
                ];
            }
        } catch (Exception $e) {
            return [
                'name' => $database,
                'size_mb' => 0,
                'connection' => $connection,
                'error' => ErrorHelper::getSafeError($e),
            ];
        }

        return [
            'name' => $database,
            'size_mb' => 0,
            'connection' => $connection,
        ];
    }

    /**
     * Get the largest tables in a database.
     */
    private function getLargestTables(string $connection, int $limit = 3): array
    {
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");

        try {
            if ($driver === 'mysql') {
                $results = DB::connection($connection)
                    ->select("SELECT
                        table_name,
                        ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                        table_rows
                    FROM information_schema.TABLES
                    WHERE table_schema = ?
                    ORDER BY (data_length + index_length) DESC
                    LIMIT {$limit}", [$database]);

                return array_map(fn ($row) => [
                    'name' => $row->table_name,
                    'size_mb' => (float) $row->size_mb,
                    'rows' => (int) $row->table_rows,
                ], $results);
            } elseif ($driver === 'sqlite') {
                // For SQLite, we can only get table names and row counts
                $tables = DB::connection($connection)
                    ->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

                $tableStats = [];
                foreach ($tables as $table) {
                    $count = DB::connection($connection)->table($table->name)->count();
                    $tableStats[] = [
                        'name' => $table->name,
                        'size_mb' => 0, // SQLite doesn't provide per-table size easily
                        'rows' => $count,
                    ];
                }

                // Sort by row count
                usort($tableStats, fn ($a, $b) => $b['rows'] <=> $a['rows']);

                return array_slice($tableStats, 0, $limit);
            }
        } catch (Exception $e) {
            return [];
        }

        return [];
    }

    /**
     * Backup a database using mysqldump.
     */
    private function backupDatabase(string $connection, string $outputFile): void
    {
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'mysql') {
            $host = config("database.connections.{$connection}.host");
            $port = config("database.connections.{$connection}.port");
            $database = config("database.connections.{$connection}.database");
            $username = config("database.connections.{$connection}.username");
            $password = config("database.connections.{$connection}.password");

            $command = sprintf(
                'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database),
                escapeshellarg($outputFile),
            );

            $process = Process::fromShellCommandline($command);
            $process->setTimeout(300); // 5 minutes timeout
            $process->run();

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
        } elseif ($driver === 'sqlite') {
            $database = config("database.connections.{$connection}.database");
            if (file_exists($database)) {
                copy($database, $outputFile);
            }
        }
    }
}
