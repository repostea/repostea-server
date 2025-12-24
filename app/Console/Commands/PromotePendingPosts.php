<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

final class PromotePendingPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:promote-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Promote the highest-voted pending post to frontpage when there is space';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $maxPosts = (int) config('posts.max_frontpage_posts', 24);
        $minVotes = (int) config('posts.frontpage_min_votes', 2);
        $maxAgeHours = (int) config('posts.frontpage_max_age_hours', 48);
        $promotionChance = (int) config('posts.frontpage_promotion_chance', 50);

        // Random chance to skip this execution (makes timing more organic)
        if (mt_rand(1, 100) > $promotionChance) {
            $this->info("Skipped this execution (random chance: {$promotionChance}%)");

            return Command::SUCCESS;
        }

        // Count posts currently on frontpage (in the last 24 hours)
        $frontpageCount = Post::whereNotNull('frontpage_at')
            ->where('frontpage_at', '>=', now()->subHours(24))
            ->count();

        $this->info("Current frontpage count: {$frontpageCount}/{$maxPosts}");

        // If frontpage is full, nothing to do
        if ($frontpageCount >= $maxPosts) {
            $this->info('Frontpage is full, no promotion needed.');

            return Command::SUCCESS;
        }

        // Calculate how many slots are available
        $availableSlots = $maxPosts - $frontpageCount;
        $this->info("Available slots: {$availableSlots}");

        // Only promote 1 post per execution to avoid flooding
        $availableSlots = min($availableSlots, 1);

        // Find pending posts that meet the criteria, ordered by votes
        // Tie-breakers: more comments first, then oldest published first
        $pendingPosts = Post::whereNull('frontpage_at')
            ->where('status', 'published')
            ->where('votes_count', '>=', $minVotes)
            ->where('published_at', '>=', now()->subHours($maxAgeHours))
            ->orderBy('votes_count', 'desc')
            ->orderBy('comment_count', 'desc')
            ->orderBy('published_at', 'asc')
            ->limit($availableSlots)
            ->get();

        if ($pendingPosts->isEmpty()) {
            $this->info('No pending posts meet the promotion criteria.');

            return Command::SUCCESS;
        }

        $promoted = 0;
        foreach ($pendingPosts as $post) {
            $post->frontpage_at = now();
            $post->save();
            $promoted++;
            $this->info("Promoted: \"{$post->title}\" ({$post->votes_count} votes)");
        }

        $this->info("Promoted {$promoted} post(s) to frontpage.");

        return Command::SUCCESS;
    }
}
