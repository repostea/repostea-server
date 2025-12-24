<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use App\Models\PostViewExtended;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles post view and impression tracking with rate limiting.
 */
final class ViewService
{
    public function __construct(
        private readonly RealtimeBroadcastService $realtimeService,
    ) {}

    public function registerView(
        Post $post,
        ?string $ip,
        ?int $userId,
        ?string $userAgent,
        ?string $referer = null,
        ?array $utmParams = null,
        ?string $screenResolution = null,
        ?string $sessionId = null,
        ?string $language = null,
    ): bool {
        // IP address is required for view tracking
        if ($ip === null) {
            return false;
        }

        // Create cache key based on user ID (if authenticated) or IP
        $viewKey = $userId
            ? 'post_view_' . $post->id . '_user_' . $userId
            : 'post_view_' . $post->id . '_ip_' . $ip;

        Log::info('ViewService: registerView called', [
            'post_id' => $post->id,
            'user_id' => $userId,
            'ip' => $ip,
            'cache_key' => $viewKey,
            'cache_exists' => Cache::has($viewKey),
            'current_views' => $post->views,
        ]);

        // Check if view was registered very recently (1 minute cooldown)
        if (Cache::has($viewKey)) {
            Log::info('ViewService: Cooldown active - rejecting view', [
                'cache_key' => $viewKey,
            ]);

            return false;
        }

        // Create extended tracking record (stores every visit)
        PostViewExtended::create([
            'post_id' => $post->id,
            'user_id' => $userId,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'referer' => $referer,
            'utm_source' => $utmParams['utm_source'] ?? null,
            'utm_medium' => $utmParams['utm_medium'] ?? null,
            'utm_campaign' => $utmParams['utm_campaign'] ?? null,
            'utm_term' => $utmParams['utm_term'] ?? null,
            'utm_content' => $utmParams['utm_content'] ?? null,
            'screen_resolution' => $screenResolution,
            'session_id' => $sessionId,
            'language' => $language,
            'visited_at' => now(),
        ]);

        // Always increment total_views for every visit (not just unique)
        $post->increment('total_views');

        $this->checkFrequency($ip);

        $now = now();

        if ($userId) {
            // For authenticated users: update or create view record
            $viewRecord = DB::table('post_views')
                ->where('post_id', $post->id)
                ->where('user_id', $userId)
                ->first();

            if ($viewRecord) {
                // Update existing view record
                DB::table('post_views')
                    ->where('id', $viewRecord->id)
                    ->update([
                        'last_visited_at' => $now,
                        'updated_at' => $now,
                        'ip_address' => $ip,
                        'user_agent' => $userAgent,
                    ]);
            } else {
                // First visit - create new record and increment views
                DB::table('post_views')->insert([
                    'post_id' => $post->id,
                    'ip_address' => $ip,
                    'user_id' => $userId,
                    'user_agent' => $userAgent,
                    'last_visited_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                Log::info('ViewService: Incrementing views (authenticated user, first visit)', [
                    'post_id' => $post->id,
                    'user_id' => $userId,
                ]);
                $post->increment('views');

                // Clear user's pending count cache since they've now visited a post
                Cache::tags(["user_{$userId}_pending"])->flush();
            }
        } else {
            // For anonymous users: use IP + User Agent as identifier
            // Check if this IP + User Agent combo has already viewed this post
            $viewRecord = DB::table('post_views')
                ->where('post_id', $post->id)
                ->where('ip_address', $ip)
                ->where('user_agent', $userAgent)
                ->whereNull('user_id')
                ->first();

            if ($viewRecord) {
                // Update existing view record - don't increment counter
                DB::table('post_views')
                    ->where('id', $viewRecord->id)
                    ->update([
                        'last_visited_at' => $now,
                        'updated_at' => $now,
                    ]);
            } else {
                // First visit from this IP + User Agent - create new record and increment views
                DB::table('post_views')->insert([
                    'post_id' => $post->id,
                    'ip_address' => $ip,
                    'user_id' => null,
                    'user_agent' => $userAgent,
                    'last_visited_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                Log::info('ViewService: Incrementing views (anonymous user, first visit)', [
                    'post_id' => $post->id,
                    'ip' => $ip,
                ]);
                $post->increment('views');
            }
        }

        Log::info('ViewService: Setting cache and returning true', [
            'cache_key' => $viewKey,
            'ttl_minutes' => 1,
        ]);

        Cache::put($viewKey, true, now()->addMinutes(1));

        // Also count as impression (for direct access without listing)
        $this->registerImpressionForView($post->id, $ip, $userId);

        // Queue realtime broadcast for views update
        $post->refresh();
        $this->realtimeService->queueViewsChange($post);

        return true;
    }

    /**
     * Register impressions for multiple posts in batch
     * Uses simple increment without duplicate checking for performance.
     */
    public function registerImpressions(array $postIds, ?string $ip, ?int $userId): int
    {
        if (empty($postIds) || $ip === null) {
            return 0;
        }

        // Rate limit: max 100 impressions per IP per minute
        $rateLimitKey = 'impressions_rate_' . $ip;
        $currentCount = (int) Cache::get($rateLimitKey, 0);

        if ($currentCount >= 100) {
            return 0;
        }

        // Filter to valid integer IDs and limit batch size
        $validIds = array_slice(
            array_filter($postIds, fn ($id) => is_numeric($id) && $id > 0),
            0,
            50, // Max 50 posts per batch
        );

        if (empty($validIds)) {
            return 0;
        }

        // Create cache key for deduplication per session
        $sessionKey = $userId ? "user_{$userId}" : "ip_{$ip}";

        // Filter out posts already seen in this session (24h window)
        $unseenIds = [];
        foreach ($validIds as $postId) {
            $cacheKey = "impression_{$sessionKey}_{$postId}";
            if (! Cache::has($cacheKey)) {
                $unseenIds[] = $postId;
                Cache::put($cacheKey, true, now()->addHours(24));
            }
        }

        if (empty($unseenIds)) {
            return 0;
        }

        // Increment impressions for all unseen posts in one query
        $updated = Post::whereIn('id', $unseenIds)->increment('impressions');

        // Update rate limit counter
        Cache::put($rateLimitKey, $currentCount + count($unseenIds), now()->addMinute());

        return $updated;
    }

    /**
     * Register a single impression when viewing a post directly.
     * Uses same deduplication logic as batch impressions.
     */
    private function registerImpressionForView(int $postId, ?string $ip, ?int $userId): void
    {
        if ($ip === null) {
            return;
        }

        // Create cache key for deduplication per session (same as batch method)
        $sessionKey = $userId ? "user_{$userId}" : "ip_{$ip}";
        $cacheKey = "impression_{$sessionKey}_{$postId}";

        // Check if already counted as impression in this session
        if (Cache::has($cacheKey)) {
            return;
        }

        // Mark as seen and increment impression
        Cache::put($cacheKey, true, now()->addHours(24));
        Post::where('id', $postId)->increment('impressions');
    }

    private function checkFrequency(?string $ip): void
    {
        if ($ip === null) {
            return;
        }

        $key = 'ip_frequency_' . $ip;

        if (Cache::has($key . '_restricted')) {
            return;
        }

        $count = (int) Cache::get($key, 0);

        $count++;

        if ($count > 30) {
            Cache::put($key . '_restricted', true, now()->addHours(1));
            Cache::forget($key);
        } else {
            Cache::put($key, $count, now()->addMinutes(5));
        }
    }
}
