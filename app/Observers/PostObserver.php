<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\SubPostCreated;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;

final class PostObserver
{
    /**
     * Handle the Post "created" event.
     */
    public function created(Post $post): void
    {
        // If the post belongs to a sub, fire event and update counter
        if ($post->sub_id && $post->sub) {
            event(new SubPostCreated($post->sub, $post));
            $this->updatePostsCount($post->sub);
        }

        // Clear post listing caches
        $this->clearPostCaches();
    }

    /**
     * Handle the Post "deleted" event.
     */
    public function deleted(Post $post): void
    {
        // If the post belongs to a sub, update counter
        if ($post->sub_id && $post->sub) {
            $this->updatePostsCount($post->sub);
        }

        // Clear post listing caches
        $this->clearPostCaches();
    }

    /**
     * Handle the Post "updated" event.
     * Note: For updating sub_id changes, the counter update happens through
     * created/deleted events when the post relationship changes.
     */
    public function updated(Post $post): void
    {
        // If sub_id changed, recalculate counters for both subs
        if ($post->wasChanged('sub_id')) {
            // Recalculate all involved counters
            $allSubIds = array_filter([$post->getOriginal('sub_id'), $post->sub_id]);
            foreach ($allSubIds as $subId) {
                $sub = \App\Models\Sub::find($subId);
                if ($sub) {
                    $this->updatePostsCount($sub);
                }
            }
        }

        // Clear post listing caches if important fields changed
        if ($post->wasChanged(['title', 'status', 'votes_count', 'comment_count', 'sub_id', 'frontpage_at'])) {
            $this->clearPostCaches();
        }

        // Clear specific post cache
        Cache::forget("post_{$post->id}");
        Cache::forget("post_slug_{$post->slug}");
    }

    /**
     * Update the post counter of a sub with the actual value.
     */
    private function updatePostsCount(\App\Models\Sub $sub): void
    {
        $count = Post::where('sub_id', $sub->id)->count();
        $sub->update(['posts_count' => $count]);

        // Check sub achievements after updating count
        $achievementService = app(\App\Services\AchievementService::class);
        $achievementService->checkSubPostsAchievements($sub);
    }

    /**
     * Clear all post listing caches.
     * This clears caches that depend on the list of posts changing.
     */
    private function clearPostCaches(): void
    {
        // Flush all caches tagged with 'posts' (Redis supports tags)
        // This automatically clears:
        // - All post listings (frontpage, pending, etc.)
        // - Pending count caches
        // - Recent posts count
        // - RSS feeds (also tagged with 'rss')
        // - Activity feed (also tagged with 'activity')
        Cache::tags(['posts'])->flush();
    }
}
