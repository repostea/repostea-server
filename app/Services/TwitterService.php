<?php

declare(strict_types=1);

namespace App\Services;

use Abraham\TwitterOAuth\TwitterOAuth;
use App\Models\Post;
use App\Models\Sub;
use App\Models\SystemSetting;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class TwitterService
{
    private ?TwitterOAuth $connection = null;

    public function __construct(
        private readonly UrlValidationService $urlValidator,
    ) {
        if ($this->isConfigured()) {
            $this->connection = new TwitterOAuth(
                config('twitter.api_key'),
                config('twitter.api_secret'),
                config('twitter.access_token'),
                config('twitter.access_token_secret'),
            );
            $this->connection->setApiVersion('2');
        }
    }

    /**
     * Check if Twitter API is properly configured.
     */
    public function isConfigured(): bool
    {
        return ! empty(config('twitter.api_key'))
            && ! empty(config('twitter.api_secret'))
            && ! empty(config('twitter.access_token'))
            && ! empty(config('twitter.access_token_secret'));
    }

    /**
     * Check if auto-posting is enabled.
     * Checks DB first, then falls back to config.
     */
    public function isAutoPostEnabled(): bool
    {
        return (bool) SystemSetting::get('twitter_auto_post_enabled', config('twitter.auto_post_enabled', false));
    }

    /**
     * Get minimum votes required for auto-posting.
     */
    public function getMinVotesToPost(): int
    {
        return (int) SystemSetting::get('twitter_min_votes', config('twitter.min_votes_to_post', 50));
    }

    /**
     * Get delay in minutes before posting.
     */
    public function getPostDelayMinutes(): int
    {
        return (int) SystemSetting::get('twitter_post_delay_minutes', config('twitter.post_delay_minutes', 30));
    }

    /**
     * Check if auto-posting original articles is enabled.
     */
    public function isAutoPostArticlesEnabled(): bool
    {
        return (bool) SystemSetting::get('twitter_auto_post_articles', config('twitter.auto_post_original_articles', true));
    }

    /**
     * Get maximum days back to check for auto-posting.
     */
    public function getMaxDaysBack(): int
    {
        return (int) SystemSetting::get('twitter_max_days_back', 3);
    }

    /**
     * Post a tweet for a given Post model.
     */
    public function postTweet(Post $post): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('TwitterService: API not configured, skipping tweet');

            return false;
        }

        if ($post->twitter_posted_at !== null) {
            Log::info('TwitterService: Post already tweeted', ['post_id' => $post->id]);

            return false;
        }

        $tweetText = $this->formatTweet($post);

        try {
            $tweetData = ['text' => $tweetText];

            // Upload image if post has thumbnail and is not NSFW
            $mediaId = $this->uploadPostImage($post);
            if ($mediaId !== null) {
                $tweetData['media'] = ['media_ids' => [$mediaId]];
            }

            /** @var object{data?: object{id?: string}} $response */
            $response = $this->connection->post('tweets', $tweetData);

            if (isset($response->data, $response->data->id)) {
                $post->twitter_posted_at = now();
                $post->twitter_tweet_id = $response->data->id;
                // Method and reason should be set by the caller before calling postTweet
                // If not set, default to 'auto' method
                if (empty($post->twitter_post_method)) {
                    $post->twitter_post_method = 'auto';
                }
                $post->saveQuietly();

                Log::info('TwitterService: Tweet posted successfully', [
                    'post_id' => $post->id,
                    'tweet_id' => $response->data->id,
                    'method' => $post->twitter_post_method,
                    'reason' => $post->twitter_post_reason,
                ]);

                return true;
            }

            Log::error('TwitterService: Failed to post tweet', [
                'post_id' => $post->id,
                'response' => json_encode($response),
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('TwitterService: Exception while posting tweet', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Upload post image to Twitter and return media_id.
     * Returns null if no image, NSFW, or upload fails.
     */
    private function uploadPostImage(Post $post): ?string
    {
        // Don't upload images for NSFW posts
        if ($post->is_nsfw) {
            return null;
        }

        // Check if post has a thumbnail
        $imageUrl = $post->thumbnail_url;
        if (empty($imageUrl)) {
            return null;
        }

        // Validate thumbnail URL to prevent SSRF
        try {
            $this->urlValidator->validate($imageUrl);
        } catch (InvalidArgumentException $e) {
            Log::warning('TwitterService: Invalid thumbnail URL rejected', [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        try {
            // Download image
            $imageContent = file_get_contents($imageUrl);
            if ($imageContent === false) {
                Log::warning('TwitterService: Could not download image', ['url' => $imageUrl]);

                return null;
            }

            // Save to temp file
            $tempFile = tempnam(sys_get_temp_dir(), 'twitter_');
            file_put_contents($tempFile, $imageContent);

            // Upload to Twitter (use v1.1 for media upload)
            $this->connection->setApiVersion('1.1');

            /** @var object{media_id_string?: string} $media */
            $media = $this->connection->upload('media/upload', ['media' => $tempFile]);

            // Restore v2
            $this->connection->setApiVersion('2');

            // Clean up temp file
            unlink($tempFile);

            if (isset($media->media_id_string)) {
                return $media->media_id_string;
            }

            Log::warning('TwitterService: Media upload failed', ['response' => json_encode($media)]);

            return null;
        } catch (Exception $e) {
            Log::warning('TwitterService: Error uploading image', ['error' => $e->getMessage()]);
            $this->connection->setApiVersion('2');

            return null;
        }
    }

    /**
     * Format the tweet text for a post.
     */
    public function formatTweet(Post $post): string
    {
        $parts = [];

        // Add title
        $parts[] = $post->title;

        // Add content summary if available (for text posts)
        if (! empty($post->content)) {
            // Twitter limit is 280 chars, reserve space for URL (~30) and hashtags (~20)
            $maxContentLength = 150;
            $content = strip_tags($post->content);
            $content = Str::limit($content, $maxContentLength, '...');
            if (! empty($content)) {
                $parts[] = $content;
            }
        }

        // Add URL
        $postUrl = $this->getPostUrl($post);
        $parts[] = $postUrl;

        // Add hashtags
        $hashtags = config('twitter.default_hashtags', []);
        if (! empty($hashtags)) {
            $hashtagString = implode(' ', array_map(fn ($tag) => '#' . $tag, $hashtags));
            $parts[] = $hashtagString;
        }

        return implode("\n\n", $parts);
    }

    /**
     * Get the public URL for a post (frontend URL).
     */
    private function getPostUrl(Post $post): string
    {
        // Use frontend URL, not backend
        $baseUrl = rtrim((string) config('app.client_url', config('app.url')), '/');

        // Use the sub slug and post slug to build the URL
        /** @var Sub|null $sub */
        $sub = $post->sub;
        if ($sub !== null) {
            /** @var string $subSlug */
            $subSlug = $sub->getAttribute('slug');

            return "{$baseUrl}/s/{$subSlug}/{$post->slug}";
        }

        return "{$baseUrl}/posts/{$post->slug}";
    }

    /**
     * Check if a post meets the criteria for auto-posting based on votes.
     * Criteria: published, on frontpage, min votes, delay.
     */
    public function shouldPostByVotes(Post $post): bool
    {
        if (! $this->isAutoPostEnabled()) {
            return false;
        }

        if ($post->twitter_posted_at !== null) {
            return false;
        }

        // Must be published
        if ($post->status !== Post::STATUS_PUBLISHED) {
            return false;
        }

        // Must be on frontpage
        if ($post->frontpage_at === null) {
            return false;
        }

        // Must have been on frontpage for the configured delay (to allow editing)
        $delayMinutes = $this->getPostDelayMinutes();
        if ($post->frontpage_at->diffInMinutes(now()) < $delayMinutes) {
            return false;
        }

        $minVotes = $this->getMinVotesToPost();

        return $post->votes_count >= $minVotes;
    }

    /**
     * Check if a post should be auto-posted as an original article.
     * Criteria: content_type = 'text', published, on frontpage, delay.
     */
    public function shouldPostAsOriginalArticle(Post $post): bool
    {
        if (! $this->isAutoPostEnabled()) {
            return false;
        }

        if (! $this->isAutoPostArticlesEnabled()) {
            return false;
        }

        if ($post->twitter_posted_at !== null) {
            return false;
        }

        // Must be a text post (original article written on the platform)
        if ($post->content_type !== 'text') {
            return false;
        }

        // Must be published
        if ($post->status !== Post::STATUS_PUBLISHED) {
            return false;
        }

        // Must be on frontpage
        if ($post->frontpage_at === null) {
            return false;
        }

        // Must have been on frontpage for the configured delay (to allow editing)
        $delayMinutes = $this->getPostDelayMinutes();
        if ($post->frontpage_at->diffInMinutes(now()) < $delayMinutes) {
            return false;
        }

        return true;
    }
}
