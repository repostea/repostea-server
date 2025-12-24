<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Post;
use App\Services\TwitterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class PostToTwitter implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $postId,
        public string $reason = 'manual',
        public string $method = 'auto',
        public ?int $postedBy = null,
    ) {}

    public function handle(TwitterService $twitterService): void
    {
        $post = Post::find($this->postId);

        if ($post === null) {
            Log::warning('PostToTwitter: Post not found', ['post_id' => $this->postId]);

            return;
        }

        if ($post->status !== 'published') {
            Log::info('PostToTwitter: Post not published, skipping', ['post_id' => $this->postId]);

            return;
        }

        if ($post->twitter_posted_at !== null) {
            Log::info('PostToTwitter: Post already tweeted', ['post_id' => $this->postId]);

            return;
        }

        Log::info('PostToTwitter: Attempting to tweet', [
            'post_id' => $this->postId,
            'reason' => $this->reason,
            'method' => $this->method,
        ]);

        // Set the method and reason before posting
        $post->twitter_post_method = $this->method;
        $post->twitter_post_reason = $this->reason;
        $post->twitter_posted_by = $this->postedBy;

        $twitterService->postTweet($post);
    }
}
