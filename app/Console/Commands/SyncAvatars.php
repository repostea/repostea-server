<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class SyncAvatars extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mbin:sync-avatars {--limit= : Limit number of users to check (default: all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync user avatars from Mbin to Repostea for existing users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Starting avatar synchronization from Mbin...');
        Log::info('Avatar sync started');

        $limit = $this->option('limit');

        try {
            $query = DB::table('users')
                ->select('id', 'username', 'avatar_url');

            if ($limit) {
                $query->limit((int) $limit);
            }

            $reposteaUsers = $query->get();

            if ($reposteaUsers->isEmpty()) {
                $this->info('No users found in Repostea');

                return Command::SUCCESS;
            }

            $usernames = $reposteaUsers->pluck('username')->toArray();

            $mbinAvatars = DB::connection('mbin')
                ->table('user as u')
                ->leftJoin('image as i', 'u.avatar_id', '=', 'i.id')
                ->whereIn('u.username', $usernames)
                ->select('u.username', 'u.avatar_id', 'i.file_path')
                ->get()
                ->keyBy('username');

            $bar = $this->output->createProgressBar($reposteaUsers->count());
            $bar->start();

            $updated = 0;
            $skipped = 0;
            $removed = 0;

            foreach ($reposteaUsers as $reposteaUser) {
                $mbinUser = $mbinAvatars->get($reposteaUser->username);

                if (! $mbinUser) {
                    // User not found in Mbin, skip
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $mbinAvatarUrl = null;
                if ($mbinUser->avatar_id && $mbinUser->file_path) {
                    $mbinAvatarUrl = config('database.connections.mbin.url', 'https://example.com') . '/media/' . $mbinUser->file_path;
                }

                if ($mbinAvatarUrl !== $reposteaUser->avatar_url) {
                    DB::table('users')
                        ->where('id', $reposteaUser->id)
                        ->update(['avatar_url' => $mbinAvatarUrl]);

                    if ($mbinAvatarUrl === null) {
                        $this->newLine();
                        $this->comment("  Removed avatar for: {$reposteaUser->username}");
                        $removed++;
                    } else {
                        $this->newLine();
                        $this->info("  Updated avatar for: {$reposteaUser->username}");
                        $updated++;
                    }

                    Log::info("Avatar synced for user {$reposteaUser->username}", [
                        'old' => $reposteaUser->avatar_url,
                        'new' => $mbinAvatarUrl,
                    ]);
                } else {
                    $skipped++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            // Resumen
            $this->info('✅ Synchronization completed!');
            $this->table(
                ['Status', 'Count'],
                [
                    ['Updated', $updated],
                    ['Removed', $removed],
                    ['Skipped (no changes)', $skipped],
                    ['Total processed', $reposteaUsers->count()],
                ],
            );

            Log::info('Avatar sync completed', [
                'updated' => $updated,
                'removed' => $removed,
                'skipped' => $skipped,
            ]);

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("Error during synchronization: {$e->getMessage()}");
            Log::error("Avatar sync failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
