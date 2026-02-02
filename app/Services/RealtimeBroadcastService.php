<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\PostStatsUpdated;
use App\Models\Post;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing real-time broadcast of post stats with dynamic throttling.
 *
 * Instead of broadcasting every single vote/view immediately, this service:
 * 1. Accumulates changes in Redis/cache
 * 2. Periodically flushes accumulated changes via a scheduled job
 * 3. Adjusts throttle interval based on connected users count
 */
final class RealtimeBroadcastService
{
    private const CACHE_PREFIX = 'realtime:pending:';

    private const CACHE_TTL = 300; // 5 minutes max TTL for pending updates

    private const CONNECTIONS_CACHE_KEY = 'realtime:connections_count';

    /**
     * Channel types for different contexts.
     */
    public const CHANNEL_FRONTPAGE = 'posts.frontpage';

    public const CHANNEL_PENDING = 'posts.pending';

    public const CHANNEL_SUB_PREFIX = 'sub.';

    public const CHANNEL_POST_PREFIX = 'post.';

    /**
     * Queue a post stats update for batched broadcasting.
     *
     * @param  int  $postId  The post ID
     * @param  array<string, int>  $changes  Changes like ['votes' => 5, 'comments_count' => 1]
     * @param  array<string>  $channels  Channels to broadcast to
     */
    public function queueUpdate(int $postId, array $changes, array $channels): void
    {
        foreach ($channels as $channel) {
            $cacheKey = self::CACHE_PREFIX . $channel;

            // Get existing pending updates for this channel
            $pending = Cache::get($cacheKey, []);

            // Merge changes for this post
            if (isset($pending[$postId])) {
                $pending[$postId] = array_merge($pending[$postId], $changes);
            } else {
                $pending[$postId] = array_merge(['id' => $postId], $changes);
            }

            Cache::put($cacheKey, $pending, self::CACHE_TTL);
        }
    }

    /**
     * Queue a vote change for a post.
     */
    public function queueVoteChange(Post $post, int $votesDelta): void
    {
        $newVotes = $post->votes_count;
        $channels = $this->getChannelsForPost($post);

        $this->queueUpdate($post->id, ['votes' => $newVotes], $channels);
    }

    /**
     * Queue a comment count change for a post.
     */
    public function queueCommentChange(Post $post, int $commentsDelta): void
    {
        $newCount = $post->comment_count;
        $channels = $this->getChannelsForPost($post);

        $this->queueUpdate($post->id, ['comments_count' => $newCount], $channels);
    }

    /**
     * Queue a views change for a post.
     */
    public function queueViewsChange(Post $post): void
    {
        $channels = $this->getChannelsForPost($post);

        $this->queueUpdate($post->id, [
            'views' => $post->views,
            'total_views' => $post->total_views,
        ], $channels);
    }

    /**
     * Get all channels a post should broadcast to.
     *
     * @return array<string>
     */
    public function getChannelsForPost(Post $post): array
    {
        $channels = [];

        // Always include the specific post channel
        $channels[] = self::CHANNEL_POST_PREFIX . $post->id;

        // Add to frontpage channel if published
        if ($post->status === Post::STATUS_PUBLISHED) {
            $channels[] = self::CHANNEL_FRONTPAGE;
        }

        // Add to pending channel if pending
        if ($post->status === Post::STATUS_PENDING) {
            $channels[] = self::CHANNEL_PENDING;
        }

        // Add to sub channel
        if ($post->sub_id !== null) {
            $channels[] = self::CHANNEL_SUB_PREFIX . $post->sub_id;
        }

        return $channels;
    }

    /**
     * Flush all pending updates to their respective channels.
     * Called by the scheduled job.
     */
    public function flushPendingUpdates(): void
    {
        $channelPatterns = [
            self::CHANNEL_FRONTPAGE,
            self::CHANNEL_PENDING,
        ];

        // Get all pending channel keys from cache
        $allKeys = $this->getAllPendingChannelKeys();

        foreach ($allKeys as $cacheKey) {
            $channel = str_replace(self::CACHE_PREFIX, '', $cacheKey);
            $pending = Cache::pull($cacheKey, []);

            if (empty($pending)) {
                continue;
            }

            // Convert to array of updates
            $updates = array_values($pending);

            // Broadcast the batch
            try {
                broadcast(new PostStatsUpdated($channel, $updates));

                Log::debug('Realtime broadcast sent', [
                    'channel' => $channel,
                    'updates_count' => count($updates),
                ]);
            } catch (Exception $e) {
                Log::error('Realtime broadcast failed', [
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);

                // Put updates back in cache to retry next cycle
                Cache::put($cacheKey, $pending, self::CACHE_TTL);
            }
        }
    }

    /**
     * Get all pending channel cache keys.
     *
     * @return array<string>
     */
    private function getAllPendingChannelKeys(): array
    {
        // For Redis, we can use keys pattern matching
        // For other drivers, we maintain a list of active channels
        $activeChannelsKey = 'realtime:active_channels';
        $activeChannels = Cache::get($activeChannelsKey, []);

        $keys = [];
        foreach ($activeChannels as $channel) {
            $key = self::CACHE_PREFIX . $channel;
            if (Cache::has($key)) {
                $keys[] = $key;
            }
        }

        // Also check known static channels
        $staticChannels = [
            self::CHANNEL_FRONTPAGE,
            self::CHANNEL_PENDING,
        ];

        foreach ($staticChannels as $channel) {
            $key = self::CACHE_PREFIX . $channel;
            if (Cache::has($key) && ! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Update the connected users count (called from Reverb presence channel).
     */
    public function updateConnectionsCount(int $count): void
    {
        Cache::put(self::CONNECTIONS_CACHE_KEY, $count, 3600);
    }

    /**
     * Get the current connected users count.
     */
    public function getConnectionsCount(): int
    {
        return (int) Cache::get(self::CONNECTIONS_CACHE_KEY, 0);
    }

    /**
     * Calculate the optimal throttle interval based on connected users.
     * More users = longer interval to reduce server load.
     *
     * @return int Interval in seconds
     */
    public function getThrottleInterval(): int
    {
        $connections = $this->getConnectionsCount();

        return match (true) {
            $connections < 50 => 2,      // Very few users: 2 seconds
            $connections < 100 => 3,     // Low traffic: 3 seconds
            $connections < 250 => 5,     // Medium traffic: 5 seconds
            $connections < 500 => 8,     // High traffic: 8 seconds
            $connections < 1000 => 12,   // Very high traffic: 12 seconds
            default => 15,               // Extreme traffic: 15 seconds
        };
    }

    /**
     * Check if we should flush updates based on dynamic throttle.
     * Used by the scheduler to decide when to run.
     */
    public function shouldFlush(): bool
    {
        $lastFlushKey = 'realtime:last_flush';
        $lastFlush = (int) Cache::get($lastFlushKey, 0);
        $interval = $this->getThrottleInterval();

        if (time() - $lastFlush >= $interval) {
            Cache::put($lastFlushKey, time(), 3600);

            return true;
        }

        return false;
    }
}
