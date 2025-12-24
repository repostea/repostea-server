<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ActivityPubFollower;
use App\Services\ActivityPubService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to send Delete activity for a post to all ActivityPub followers.
 */
final class DeleteActivityPubPost implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    public function __construct(
        public readonly int $postId,
        public readonly string $postSlug,
        public readonly bool $legacy = false,
    ) {
        $this->onQueue(config('activitypub.queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(ActivityPubService $activityPub): void
    {
        if (! $activityPub->isEnabled()) {
            Log::debug('ActivityPub: Delete skipped - not enabled');

            return;
        }

        $followerCount = ActivityPubFollower::count();
        if ($followerCount === 0) {
            Log::debug('ActivityPub: No followers to send Delete to');

            return;
        }

        $mode = $this->legacy ? 'legacy' : 'standard';
        Log::info("ActivityPub: Sending Delete ({$mode}) for post {$this->postId} to {$followerCount} followers");

        // Build the Delete activity (legacy or standard format)
        $activity = $this->legacy
            ? $activityPub->buildLegacyDeleteActivity($this->postId, $this->postSlug)
            : $activityPub->buildDeleteActivity($this->postId, $this->postSlug);

        // Get unique inboxes
        $inboxes = ActivityPubFollower::getUniqueInboxes();

        foreach ($inboxes as $inbox) {
            // Dispatch individual delivery job
            DeliverToInbox::dispatch($activity['id'], $inbox, $activity);
        }
    }
}
