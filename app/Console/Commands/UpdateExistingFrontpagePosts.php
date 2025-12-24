<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

final class UpdateExistingFrontpagePosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:update-frontpage-dates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update frontpage_at for existing posts that are already in frontpage';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating frontpage_at for existing posts...');

        // Get the current dynamic threshold
        $boostEnabled = (bool) config('posts.low_activity_boost_enabled', true);
        $threshold = 3; // Default threshold

        if ($boostEnabled) {
            $hoursToCheck = (int) config('posts.low_activity_check_hours', 24);
            $recentPostsCount = Post::where('created_at', '>=', now()->subHours($hoursToCheck))->count();
            $minPostsRequired = (int) config('posts.low_activity_min_posts', 24);

            $threshold = $recentPostsCount < $minPostsRequired
                ? (int) config('posts.low_activity_threshold', 1)
                : (int) config('posts.frontpage_votes_threshold', 3);
        }

        $this->info("Current threshold: {$threshold} votes");

        // Find posts that meet the threshold but don't have frontpage_at set
        $posts = Post::whereNull('frontpage_at')
            ->where('votes_count', '>=', $threshold)
            ->get();

        $this->info("Found {$posts->count()} posts to update");

        $updated = 0;
        foreach ($posts as $post) {
            // Set frontpage_at to created_at for existing posts
            // This ensures they maintain their relative position
            $post->frontpage_at = $post->created_at;
            $post->save();
            $updated++;
        }

        $this->info("Updated {$updated} posts");
        $this->info('Done!');

        return Command::SUCCESS;
    }
}
