<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class CreateMediaDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:create-media {--force : Force creation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the media database if it does not exist';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $databaseName = config('database.connections.media.database');

        if (empty($databaseName)) {
            $this->error('Media database name is not configured. Please set DB_MEDIA_DATABASE in your .env file.');

            return 1;
        }

        if (! $this->option('force') && ! $this->confirm("Create database '{$databaseName}'?", true)) {
            $this->info('Operation cancelled.');

            return 0;
        }

        try {
            // Connect to MySQL without specifying a database
            $host = config('database.connections.media.host');
            $port = config('database.connections.media.port');
            $username = config('database.connections.media.username');
            $password = config('database.connections.media.password');
            $charset = config('database.connections.media.charset', 'utf8mb4');
            $collation = config('database.connections.media.collation', 'utf8mb4_unicode_ci');

            // Create a temporary connection to the MySQL server (without database)
            config(['database.connections.temp' => [
                'driver' => 'mysql',
                'host' => $host,
                'port' => $port,
                'database' => null,
                'username' => $username,
                'password' => $password,
                'unix_socket' => '',
                'charset' => $charset,
                'collation' => $collation,
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
                'options' => [],
            ]]);

            // Check if database exists
            $databases = DB::connection('temp')
                ->select('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$databaseName]);

            if (! empty($databases)) {
                $this->info("Database '{$databaseName}' already exists.");

                return 0;
            }

            // Create the database
            DB::connection('temp')
                ->statement("CREATE DATABASE `{$databaseName}` CHARACTER SET {$charset} COLLATE {$collation}");

            $this->info("Database '{$databaseName}' created successfully!");

            // Now run migrations for the media connection
            if ($this->confirm('Run migrations for media database?', true)) {
                $this->call('migrate', ['--database' => 'media']);
            }

            return 0;
        } catch (Exception $e) {
            $this->error("Failed to create database: {$e->getMessage()}");

            return 1;
        }
    }
}
