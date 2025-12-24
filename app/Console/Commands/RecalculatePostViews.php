<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\confirm;

final class RecalculatePostViews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:recalculate-views';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze and recalculate post views by removing duplicate anonymous views';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Analyzing post views...');
        $this->newLine();

        // Count total views in post_views table
        $totalViewRecords = DB::table('post_views')->count();
        $this->line("Total view records in database: <fg=cyan>{$totalViewRecords}</>");

        // Count authenticated user views
        $authenticatedViews = DB::table('post_views')->whereNotNull('user_id')->count();
        $this->line("Authenticated user views: <fg=green>{$authenticatedViews}</>");

        // Count anonymous views
        $anonymousViews = DB::table('post_views')->whereNull('user_id')->count();
        $this->line("Anonymous user views: <fg=yellow>{$anonymousViews}</>");

        $this->newLine();

        // Find duplicate anonymous views (same post + IP + user agent)
        $duplicateCount = DB::table('post_views')
            ->select(DB::raw('COUNT(*) - 1 as duplicate_count'))
            ->whereNull('user_id')
            ->groupBy('post_id', 'ip_address', 'user_agent')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->sum('duplicate_count');

        if ($duplicateCount > 0) {
            $this->warn("⚠️  Found {$duplicateCount} duplicate anonymous views");
            $this->line('   These are multiple views from the same IP + browser to the same post');
        } else {
            $this->info('✓ No duplicate anonymous views found');
        }

        $this->newLine();

        // Calculate what the real counts would be
        $uniqueAnonymousViews = $anonymousViews - $duplicateCount;
        $realTotalViews = $authenticatedViews + $uniqueAnonymousViews;

        $this->line('After cleanup:');
        $this->line("  Unique anonymous views: <fg=green>{$uniqueAnonymousViews}</>");
        $this->line("  Total unique views: <fg=green>{$realTotalViews}</>");
        $this->line("  Views to remove: <fg=red>{$duplicateCount}</>");

        $this->newLine();

        // Show current vs real counts for posts table
        $currentPostsViewSum = DB::table('posts')->sum('views');
        $this->line("Current sum of posts.views column: <fg=cyan>{$currentPostsViewSum}</>");
        $this->line("Real sum after cleanup: <fg=green>{$realTotalViews}</>");
        $difference = $currentPostsViewSum - $realTotalViews;
        $this->line("Difference (inflated count): <fg=red>-{$difference}</>");

        $this->newLine();

        if ($duplicateCount === 0) {
            $this->info('Nothing to clean up!');

            return self::SUCCESS;
        }

        // Show top posts with most duplicate views
        $this->info('📊 Posts with most duplicate views:');
        $this->newLine();

        $topPosts = DB::table('post_views')
            ->select('post_id', DB::raw('COUNT(*) - 1 as duplicates'))
            ->whereNull('user_id')
            ->groupBy('post_id', 'ip_address', 'user_agent')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->groupBy('post_id')
            ->map(fn ($group) => $group->sum('duplicates'))
            ->sortDesc()
            ->take(10);

        foreach ($topPosts as $postId => $duplicateCount) {
            $post = DB::table('posts')->where('id', $postId)->first();
            if ($post) {
                $currentViews = $post->views;
                $realViews = DB::table('post_views')
                    ->where('post_id', $postId)
                    ->select(DB::raw('COUNT(DISTINCT CONCAT(COALESCE(user_id, ""), "-", ip_address, "-", user_agent)) as unique_count'))
                    ->value('unique_count');

                $title = mb_strlen($post->title) > 50 ? mb_substr($post->title, 0, 50) . '...' : $post->title;
                $this->line("  ID {$postId}: {$title}");
                $this->line("    Current: <fg=cyan>{$currentViews}</> | Real: <fg=green>{$realViews}</> | Duplicates: <fg=red>{$duplicateCount}</>");
            }
        }

        $this->newLine();

        // Always clean one by one
        $this->info('🔍 Reviewing posts one by one...');
        $this->newLine();

        $totalRemoved = 0;
        $postsToRecalculate = [];

        foreach ($topPosts as $postId => $duplicates) {
            $post = DB::table('posts')->where('id', $postId)->first();
            if (! $post) {
                continue;
            }

            $currentViews = $post->views;
            $realViews = DB::table('post_views')
                ->where('post_id', $postId)
                ->select(DB::raw('COUNT(DISTINCT CONCAT(COALESCE(user_id, ""), "-", ip_address, "-", user_agent)) as unique_count'))
                ->value('unique_count');

            $title = mb_strlen($post->title) > 60 ? mb_substr($post->title, 0, 60) . '...' : $post->title;

            $this->line('─────────────────────────────────────────────────────────────');
            $this->line("<fg=yellow>Post ID {$postId}</>: {$title}");
            $this->line("  Current views: <fg=cyan>{$currentViews}</>");
            $this->line("  Real views: <fg=green>{$realViews}</>");
            $this->line("  Duplicates to remove: <fg=red>{$duplicates}</>");

            if (confirm('Clean duplicates for this post?', default: false)) {
                // Remove duplicates for this specific post
                $duplicateIds = DB::table('post_views as v1')
                    ->select('v1.id')
                    ->join(DB::raw('(
                        SELECT post_id, ip_address, user_agent, MIN(id) as min_id
                        FROM post_views
                        WHERE user_id IS NULL AND post_id = ' . $postId . '
                        GROUP BY post_id, ip_address, user_agent
                        HAVING COUNT(*) > 1
                    ) as v2'), function ($join): void {
                        $join->on('v1.post_id', '=', 'v2.post_id')
                            ->on('v1.ip_address', '=', 'v2.ip_address')
                            ->on('v1.user_agent', '=', 'v2.user_agent')
                            ->whereRaw('v1.id > v2.min_id');
                    })
                    ->whereNull('v1.user_id')
                    ->where('v1.post_id', $postId)
                    ->pluck('id');

                if ($duplicateIds->isNotEmpty()) {
                    DB::table('post_views')->whereIn('id', $duplicateIds)->delete();
                    $totalRemoved += $duplicateIds->count();
                    $postsToRecalculate[] = $postId;
                    $this->info("  ✓ Removed {$duplicateIds->count()} duplicates");
                }
            } else {
                $this->line('  ⊘ Skipped');
            }

            $this->newLine();
        }

        if ($totalRemoved === 0) {
            $this->info('No duplicates were removed.');

            return self::SUCCESS;
        }

        $this->info("✓ Removed {$totalRemoved} duplicate views in total");

        // Recalculate views count
        $this->info('🔢 Recalculating post view counts...');

        if (empty($postsToRecalculate)) {
            $this->info('No posts to recalculate.');
        } else {
            // Recalculate only affected posts
            foreach ($postsToRecalculate as $postId) {
                $realCount = DB::table('post_views')->where('post_id', $postId)->count();
                DB::table('posts')->where('id', $postId)->update(['views' => $realCount]);
            }
        }

        $this->info('✓ Post view counts recalculated');
        $this->newLine();

        // Show final results
        $finalPostsViewSum = DB::table('posts')->sum('views');
        $this->line("Final sum of posts.views: <fg=green>{$finalPostsViewSum}</>");

        $this->newLine();
        $this->info('✨ Done! View counts updated.');

        return self::SUCCESS;
    }
}
