<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PostToTwitter;
use App\Models\Post;
use App\Services\TwitterService;
use Illuminate\Console\Command;

final class PostPendingToTwitter extends Command
{
    protected $signature = 'twitter:post-pending';

    protected $description = 'Post pending content to Twitter (frontpage posts after delay)';

    public function handle(TwitterService $twitterService): int
    {
        if (! $twitterService->isAutoPostEnabled()) {
            $this->info('Twitter auto-post is disabled.');

            return self::SUCCESS;
        }

        if (! $twitterService->isConfigured()) {
            $this->error('Twitter API is not configured.');

            return self::FAILURE;
        }

        $delayMinutes = $twitterService->getPostDelayMinutes();
        $minVotes = $twitterService->getMinVotesToPost();
        $autoPostArticles = $twitterService->isAutoPostArticlesEnabled();
        $maxDaysBack = $twitterService->getMaxDaysBack();

        $this->info("Config: min_votes={$minVotes}, delay={$delayMinutes}min, max_days={$maxDaysBack}, articles=" . ($autoPostArticles ? 'yes' : 'no'));

        // Find posts that should be posted to Twitter
        // Only look at posts from the last X days to prevent bulk posting old content
        $posts = Post::query()
            ->where('status', 'published')
            ->whereNotNull('frontpage_at')
            ->whereNull('twitter_posted_at')
            ->where('frontpage_at', '<=', now()->subMinutes($delayMinutes))
            ->where('frontpage_at', '>=', now()->subDays($maxDaysBack))
            ->where(function ($query) use ($minVotes, $autoPostArticles): void {
                // Posts with enough votes
                $query->where('votes_count', '>=', $minVotes);
                // OR text posts (articles) if enabled
                if ($autoPostArticles) {
                    $query->orWhere('content_type', 'text');
                }
            })
            ->get();

        if ($posts->isEmpty()) {
            $this->info('No pending posts to tweet.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($posts as $post) {
            $reason = $post->content_type === 'text' ? 'original_article' : 'popular_votes';
            PostToTwitter::dispatch($post->id, $reason, 'auto', null);
            $this->line("Queued: {$post->title} (reason: {$reason})");
            $count++;
        }

        $this->info("Queued {$count} posts for Twitter.");

        return self::SUCCESS;
    }
}
