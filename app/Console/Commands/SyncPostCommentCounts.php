<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Post;
use Exception;
use Illuminate\Console\Command;

final class SyncPostCommentCounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:sync-comment-counts {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize comment_count field with actual comment counts for all posts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
        }

        $this->info('Synchronizing post comment counts...');

        $posts = Post::all();
        $updated = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($posts->count());
        $progressBar->start();

        foreach ($posts as $post) {
            try {
                $realCount = $post->comments()->count();

                if ($post->comment_count !== $realCount) {
                    if (! $dryRun) {
                        $post->comment_count = $realCount;
                        $post->save();
                    }

                    $updated++;

                    if ($this->output->isVerbose()) {
                        $this->newLine();
                        $this->line("Post #{$post->id}: {$post->comment_count} -> {$realCount}");
                    }
                }
            } catch (Exception $e) {
                $errors++;
                if ($this->output->isVerbose()) {
                    $this->newLine();
                    $this->error("Error processing post #{$post->id}: {$e->getMessage()}");
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("DRY RUN: Would update {$updated} posts");
        } else {
            $this->info("Successfully updated {$updated} posts");
        }

        if ($errors > 0) {
            $this->warn("Encountered {$errors} errors during processing");
        }

        $unchanged = $posts->count() - $updated - $errors;
        $this->info("Posts already in sync: {$unchanged}");

        return self::SUCCESS;
    }
}
