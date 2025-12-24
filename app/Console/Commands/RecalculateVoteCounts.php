<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Console\Command;

final class RecalculateVoteCounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'votes:recalculate
                          {--posts : Only recalculate post votes}
                          {--comments : Only recalculate comment votes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate vote counts for all posts and comments';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $recalculatePosts = $this->option('posts') || (! $this->option('posts') && ! $this->option('comments'));
        $recalculateComments = $this->option('comments') || (! $this->option('posts') && ! $this->option('comments'));

        if ($recalculatePosts) {
            $this->info('Recalculating post vote counts...');
            $this->recalculatePostVotes();
        }

        if ($recalculateComments) {
            $this->info('Recalculating comment vote counts...');
            $this->recalculateCommentVotes();
        }

        $this->info('✓ Vote counts recalculated successfully!');

        return self::SUCCESS;
    }

    private function recalculatePostVotes(): void
    {
        $posts = Post::withCount('votes')->get();
        $bar = $this->output->createProgressBar($posts->count());
        $bar->start();

        $fixed = 0;

        foreach ($posts as $post) {
            $oldCount = $post->votes_count;
            $post->updateVotesCount();
            $newCount = $post->fresh()->votes_count;

            if ($oldCount !== $newCount) {
                $fixed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Fixed {$fixed} posts with incorrect vote counts");
    }

    private function recalculateCommentVotes(): void
    {
        $comments = Comment::withCount('votes')->get();
        $bar = $this->output->createProgressBar($comments->count());
        $bar->start();

        $fixed = 0;

        foreach ($comments as $comment) {
            $oldCount = $comment->votes_count;
            $comment->updateVotesCount();
            $newCount = $comment->fresh()->votes_count;

            if ($oldCount !== $newCount) {
                $fixed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Fixed {$fixed} comments with incorrect vote counts");
    }
}
