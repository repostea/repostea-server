<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Console\Command;

final class RecalculatePostsCounts extends Command
{
    protected $signature = 'posts:recalculate-counts
        {--posts : Recalculate only posts votes_count}
        {--comments : Recalculate only comments votes_count}
        {--posts-comments : Recalculate comments_count on posts}
        {--hours= : Only recalculate items from last X hours (default: all)}';

    protected $description = 'Recalculate votes_count and comments_count for posts and comments';

    public function handle(): void
    {
        $hasOptions = $this->option('posts') || $this->option('comments') || $this->option('posts-comments');

        $recalculatePosts = $this->option('posts') || ! $hasOptions;
        $recalculateComments = $this->option('comments') || ! $hasOptions;
        $recalculatePostsComments = $this->option('posts-comments') || ! $hasOptions;

        if ($recalculatePosts) {
            $this->info('🔄 Recalculating votes_count for posts...');
            $this->recalculatePostsVotes();
        }

        if ($recalculateComments) {
            $this->info('🔄 Recalculating votes_count for comments...');
            $this->recalculateCommentsVotes();
        }

        if ($recalculatePostsComments) {
            $this->info('🔄 Recalculating comments_count for posts...');
            $this->recalculatePostsCommentsCount();
        }

        $this->info('✅ Done!');
    }

    private function recalculatePostsVotes(): void
    {
        $query = Post::query();

        if ($hours = $this->option('hours')) {
            $query->where('created_at', '>=', now()->subHours((int) $hours));
            $this->info("  (Only processing posts from last {$hours} hours)");
        }

        $posts = $query->get();
        $bar = $this->output->createProgressBar($posts->count());

        $updated = 0;
        foreach ($posts as $post) {
            $actualCount = $post->votes()->count();
            if ($post->votes_count !== $actualCount) {
                $post->votes_count = $actualCount;
                $post->save();
                $updated++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Updated {$updated} posts");
    }

    private function recalculateCommentsVotes(): void
    {
        $query = Comment::query();

        if ($hours = $this->option('hours')) {
            $query->where('created_at', '>=', now()->subHours((int) $hours));
            $this->info("  (Only processing comments from last {$hours} hours)");
        }

        $comments = $query->get();
        $bar = $this->output->createProgressBar($comments->count());

        $updated = 0;
        foreach ($comments as $comment) {
            $actualCount = $comment->votes()->count();
            if ($comment->votes_count !== $actualCount) {
                $comment->votes_count = $actualCount;
                $comment->save();
                $updated++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Updated {$updated} comments");
    }

    private function recalculatePostsCommentsCount(): void
    {
        $query = Post::query();

        if ($hours = $this->option('hours')) {
            $query->where('created_at', '>=', now()->subHours((int) $hours));
            $this->info("  (Only processing posts from last {$hours} hours)");
        }

        $posts = $query->get();
        $bar = $this->output->createProgressBar($posts->count());

        $updated = 0;
        foreach ($posts as $post) {
            $actualCount = $post->comments()->count();
            if ($post->comment_count !== $actualCount) {
                $post->comment_count = $actualCount;
                $post->save();
                $updated++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Updated {$updated} posts");
    }
}
