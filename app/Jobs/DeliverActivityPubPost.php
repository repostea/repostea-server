<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ActivityPubDelivery;
use App\Models\ActivityPubFollower;
use App\Models\Post;
use App\Services\ActivityPubService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to deliver a post to all ActivityPub followers.
 */
final class DeliverActivityPubPost implements ShouldQueue
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
        public readonly Post $post,
    ) {
        $this->onQueue(config('activitypub.queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(ActivityPubService $activityPub): void
    {
        if (! $activityPub->isEnabled()) {
            Log::debug('ActivityPub: Delivery skipped - not enabled');

            return;
        }

        // Check if post is valid for federation
        if ($this->post->deleted_at !== null) {
            Log::debug("ActivityPub: Post {$this->post->id} is deleted, skipping");

            return;
        }

        if ($this->post->status !== Post::STATUS_PUBLISHED) {
            Log::debug("ActivityPub: Post {$this->post->id} is not published (status: {$this->post->status}), skipping");

            return;
        }

        $followerCount = ActivityPubFollower::count();
        if ($followerCount === 0) {
            Log::debug('ActivityPub: No followers to deliver to');

            return;
        }

        Log::info("ActivityPub: Delivering post {$this->post->id} to {$followerCount} followers");

        // Build the Create activity once
        $activity = $activityPub->buildCreateActivity($this->post);
        $activityId = $activity['id'];

        // Get unique inboxes (prefer shared inboxes for efficiency)
        $inboxes = ActivityPubFollower::getUniqueInboxes();

        foreach ($inboxes as $inbox) {
            // Check if already delivered
            $delivery = ActivityPubDelivery::firstOrCreate(
                ['activity_id' => $activityId, 'target_inbox' => $inbox],
                ['status' => ActivityPubDelivery::STATUS_PENDING],
            );

            if ($delivery->status === ActivityPubDelivery::STATUS_DELIVERED) {
                continue;
            }

            if (! $delivery->canRetry()) {
                continue;
            }

            // Dispatch individual delivery job
            DeliverToInbox::dispatch($activityId, $inbox, $activity);
        }
    }
}
