<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ActivityPubActor;
use App\Models\ActivityPubActorFollower;
use App\Models\ActivityPubPostSettings;
use App\Models\ActivityPubSubSettings;
use App\Models\Post;
use App\Services\MultiActorActivityPubService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Deliver an Update activity when a post is edited.
 *
 * Sends Update activities to all followers who received the original post:
 * - User actor followers
 * - Group actor followers (if in a federated sub)
 * - Instance actor followers
 */
final class DeliverPostUpdate implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        private readonly Post $post,
    ) {
        $this->onQueue(config('activitypub.queue', 'activitypub'));
    }

    public function handle(MultiActorActivityPubService $service): void
    {
        // Check if ActivityPub is enabled
        if (! config('activitypub.enabled', false)) {
            Log::debug('ActivityPub: Disabled, skipping update delivery');

            return;
        }

        // Refresh post to get latest status (in case it changed while in queue)
        $this->post->refresh();

        // Check if post is still published
        if ($this->post->status !== 'published') {
            Log::debug('ActivityPub: Post no longer published, skipping update', [
                'post_id' => $this->post->id,
                'status' => $this->post->status,
            ]);

            return;
        }

        // Check if post has been federated
        $postSettings = ActivityPubPostSettings::where('post_id', $this->post->id)->first();
        if ($postSettings === null || ! $postSettings->is_federated) {
            Log::debug('ActivityPub: Post not federated, skipping update', [
                'post_id' => $this->post->id,
            ]);

            return;
        }

        Log::info('ActivityPub: Starting Update delivery', [
            'post_id' => $this->post->id,
            'post_title' => $this->post->title,
        ]);

        $deliveredInboxes = [];

        // 1. Deliver from user actor
        $deliveredInboxes = $this->deliverFromUserActor($service, $deliveredInboxes);

        // 2. Deliver from group actor if post is in a sub
        $deliveredInboxes = $this->deliverFromGroupActor($service, $deliveredInboxes);

        // 3. Deliver from instance actor
        $this->deliverFromInstanceActor($service, $deliveredInboxes);

        Log::info('ActivityPub: Update delivery complete', [
            'post_id' => $this->post->id,
        ]);
    }

    /**
     * @param  array<string, bool>  $deliveredInboxes
     *
     * @return array<string, bool>
     */
    private function deliverFromUserActor(MultiActorActivityPubService $service, array $deliveredInboxes): array
    {
        $userActor = $service->getUserActor($this->post->user);

        if ($userActor === null) {
            return $deliveredInboxes;
        }

        $followers = $userActor->followers;

        if ($followers->isEmpty()) {
            return $deliveredInboxes;
        }

        $activity = $service->buildUpdateActivity($userActor, $this->post);
        $inboxes = ActivityPubActorFollower::getUniqueInboxes($followers);

        Log::info('ActivityPub: Delivering Update from user actor', [
            'actor' => $userActor->actor_uri,
            'inboxes_count' => count($inboxes),
        ]);

        foreach ($inboxes as $inbox) {
            if (isset($deliveredInboxes[$inbox])) {
                continue;
            }

            DeliverToInbox::dispatch($userActor->id, $inbox, $activity, 'multi_actor');
            $deliveredInboxes[$inbox] = true;
        }

        return $deliveredInboxes;
    }

    /**
     * @param  array<string, bool>  $deliveredInboxes
     *
     * @return array<string, bool>
     */
    private function deliverFromGroupActor(MultiActorActivityPubService $service, array $deliveredInboxes): array
    {
        if ($this->post->sub_id === null) {
            return $deliveredInboxes;
        }

        $sub = $this->post->sub;
        if ($sub === null) {
            return $deliveredInboxes;
        }

        $subSettings = ActivityPubSubSettings::where('sub_id', $sub->id)->first();
        if ($subSettings === null || ! $subSettings->federation_enabled) {
            return $deliveredInboxes;
        }

        $groupActor = $service->getGroupActor($sub);
        if ($groupActor === null) {
            return $deliveredInboxes;
        }

        $followers = $groupActor->followers;

        if ($followers->isEmpty()) {
            return $deliveredInboxes;
        }

        // Get user actor for attribution
        $userActor = $service->getUserActor($this->post->user);
        if ($userActor === null) {
            $userActor = ActivityPubActor::findOrCreateForUser($this->post->user);
        }

        // For group, we send the Update from the original author, not the group
        $activity = $service->buildUpdateActivity($userActor, $this->post);
        $inboxes = ActivityPubActorFollower::getUniqueInboxes($followers);

        Log::info('ActivityPub: Delivering Update via group actor', [
            'group_actor' => $groupActor->actor_uri,
            'inboxes_count' => count($inboxes),
        ]);

        foreach ($inboxes as $inbox) {
            if (isset($deliveredInboxes[$inbox])) {
                continue;
            }

            DeliverToInbox::dispatch($userActor->id, $inbox, $activity, 'multi_actor');
            $deliveredInboxes[$inbox] = true;
        }

        return $deliveredInboxes;
    }

    /**
     * @param  array<string, bool>  $deliveredInboxes
     */
    private function deliverFromInstanceActor(MultiActorActivityPubService $service, array $deliveredInboxes): void
    {
        $instanceActor = $service->getInstanceActor();
        $followers = $instanceActor->followers;

        $legacyFollowers = \App\Models\ActivityPubFollower::all();

        if ($followers->isEmpty() && $legacyFollowers->isEmpty()) {
            return;
        }

        $activity = $service->buildUpdateActivity($instanceActor, $this->post);

        $inboxes = [];

        foreach ($followers as $follower) {
            $inbox = $follower->getDeliveryInbox();
            $inboxes[$inbox] = true;
        }

        foreach ($legacyFollowers as $follower) {
            $inbox = $follower->shared_inbox_url ?? $follower->inbox_url;
            $inboxes[$inbox] = true;
        }

        Log::info('ActivityPub: Delivering Update from instance actor', [
            'actor' => $instanceActor->actor_uri,
            'inboxes_count' => count($inboxes),
        ]);

        foreach (array_keys($inboxes) as $inbox) {
            if (isset($deliveredInboxes[$inbox])) {
                continue;
            }

            DeliverToInbox::dispatch($instanceActor->id, $inbox, $activity, 'multi_actor');
        }
    }
}
