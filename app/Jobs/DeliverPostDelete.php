<?php

declare(strict_types=1);

namespace App\Jobs;

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
 * Deliver a Delete activity when a post is removed from the Fediverse.
 *
 * This can happen when:
 * - User explicitly unfederates a post
 * - Post is deleted
 * - Post is hidden/unpublished
 *
 * Sends Delete activities to all followers who received the original post.
 */
final class DeliverPostDelete implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        private readonly int $postId,
        private readonly int $userId,
        private readonly ?int $subId = null,
    ) {
        $this->onQueue(config('activitypub.queue', 'activitypub'));
    }

    public function handle(MultiActorActivityPubService $service): void
    {
        if (! config('activitypub.enabled', false)) {
            Log::debug('ActivityPub: Disabled, skipping delete delivery');

            return;
        }

        Log::info('ActivityPub: Starting Delete delivery', [
            'post_id' => $this->postId,
        ]);

        $deliveredInboxes = [];

        // 1. Deliver from user actor
        $deliveredInboxes = $this->deliverFromUserActor($service, $deliveredInboxes);

        // 2. Deliver from group actor if post was in a sub
        $deliveredInboxes = $this->deliverFromGroupActor($service, $deliveredInboxes);

        // 3. Deliver from instance actor
        $this->deliverFromInstanceActor($service, $deliveredInboxes);

        // Mark post as unfederated
        ActivityPubPostSettings::where('post_id', $this->postId)->update([
            'is_federated' => false,
            'federated_at' => null,
            'activitypub_id' => null,
            'activity_id' => null,
        ]);

        Log::info('ActivityPub: Delete delivery complete', [
            'post_id' => $this->postId,
        ]);
    }

    /**
     * @param  array<string, bool>  $deliveredInboxes
     *
     * @return array<string, bool>
     */
    private function deliverFromUserActor(MultiActorActivityPubService $service, array $deliveredInboxes): array
    {
        $user = \App\Models\User::find($this->userId);
        if ($user === null) {
            return $deliveredInboxes;
        }

        $userActor = $service->getUserActor($user);
        if ($userActor === null) {
            return $deliveredInboxes;
        }

        $followers = $userActor->followers;
        if ($followers->isEmpty()) {
            return $deliveredInboxes;
        }

        $activity = $service->buildDeleteActivity($userActor, $this->postId);
        $inboxes = ActivityPubActorFollower::getUniqueInboxes($followers);

        Log::info('ActivityPub: Delivering Delete from user actor', [
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
        if ($this->subId === null) {
            return $deliveredInboxes;
        }

        $sub = \App\Models\Sub::find($this->subId);
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

        // Get user actor for the Delete
        $user = \App\Models\User::find($this->userId);
        $userActor = $user !== null ? $service->getUserActor($user) : null;

        if ($userActor === null) {
            $userActor = $groupActor;
        }

        $activity = $service->buildDeleteActivity($userActor, $this->postId);
        $inboxes = ActivityPubActorFollower::getUniqueInboxes($followers);

        Log::info('ActivityPub: Delivering Delete via group actor', [
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

        $activity = $service->buildDeleteActivity($instanceActor, $this->postId);

        $inboxes = [];

        foreach ($followers as $follower) {
            $inbox = $follower->getDeliveryInbox();
            $inboxes[$inbox] = true;
        }

        foreach ($legacyFollowers as $follower) {
            $inbox = $follower->shared_inbox_url ?? $follower->inbox_url;
            $inboxes[$inbox] = true;
        }

        Log::info('ActivityPub: Delivering Delete from instance actor', [
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
