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
 * Deliver a post to the Fediverse using multi-actor architecture.
 *
 * This job handles the FEP-1b12 compliant federation:
 * 1. User actor sends Create activity to their followers
 * 2. Group actor sends Announce activity to group followers (if post is in a sub)
 * 3. Instance actor sends Create activity to instance followers
 *
 * Flow:
 * - Check if post should be federated (user opted in, post marked for federation)
 * - Get user's actor and send Create to their followers
 * - If post is in a sub with federation enabled, send Announce from group
 * - Send from instance actor to instance followers
 */
final class DeliverMultiActorPost implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
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
            Log::debug('ActivityPub: Disabled, skipping delivery');

            return;
        }

        // Refresh post to get latest status (in case it changed while in queue)
        $this->post->refresh();

        // Check if post can be federated
        if (! ActivityPubPostSettings::canFederate($this->post)) {
            Log::debug('ActivityPub: Post cannot be federated', [
                'post_id' => $this->post->id,
            ]);

            return;
        }

        Log::info('ActivityPub: Starting multi-actor delivery', [
            'post_id' => $this->post->id,
            'post_title' => $this->post->title,
        ]);

        $deliveredInboxes = [];

        // 1. Deliver from user actor (Create)
        $deliveredInboxes = $this->deliverFromUserActor($service, $deliveredInboxes);

        // 2. Deliver from group actor (Announce) if post is in a sub
        $deliveredInboxes = $this->deliverFromGroupActor($service, $deliveredInboxes);

        // 3. Deliver from instance actor (Create)
        $this->deliverFromInstanceActor($service, $deliveredInboxes);

        // Mark post as federated
        $postSettings = ActivityPubPostSettings::where('post_id', $this->post->id)->first();
        if ($postSettings !== null) {
            $domain = $service->getDomain();
            $postSettings->markAsFederated(
                "{$domain}/activitypub/notes/{$this->post->id}",
                "{$domain}/activitypub/activities/create/{$this->post->id}",
            );
        }

        Log::info('ActivityPub: Multi-actor delivery complete', [
            'post_id' => $this->post->id,
        ]);
    }

    /**
     * Deliver Create activity from user's actor.
     *
     * @param  array<string, bool>  $deliveredInboxes
     *
     * @return array<string, bool>
     */
    private function deliverFromUserActor(MultiActorActivityPubService $service, array $deliveredInboxes): array
    {
        $userActor = $service->getUserActor($this->post->user);

        if ($userActor === null) {
            Log::debug('ActivityPub: User has no actor, skipping user delivery', [
                'user_id' => $this->post->user_id,
            ]);

            return $deliveredInboxes;
        }

        $followers = $userActor->followers;

        if ($followers->isEmpty()) {
            Log::debug('ActivityPub: User actor has no followers', [
                'actor' => $userActor->actor_uri,
            ]);

            return $deliveredInboxes;
        }

        $activity = $service->buildCreateActivity($userActor, $this->post);
        $inboxes = ActivityPubActorFollower::getUniqueInboxes($followers);

        Log::info('ActivityPub: Delivering Create from user actor', [
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
     * Deliver Announce activity from group actor.
     *
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

        // Check if sub has federation enabled
        $subSettings = ActivityPubSubSettings::where('sub_id', $sub->id)->first();
        if ($subSettings === null || ! $subSettings->shouldAutoAnnounce()) {
            Log::debug('ActivityPub: Sub federation not enabled or auto-announce disabled', [
                'sub_id' => $sub->id,
            ]);

            return $deliveredInboxes;
        }

        $groupActor = $service->getGroupActor($sub);
        if ($groupActor === null) {
            return $deliveredInboxes;
        }

        $followers = $groupActor->followers;

        if ($followers->isEmpty()) {
            Log::debug('ActivityPub: Group actor has no followers', [
                'actor' => $groupActor->actor_uri,
            ]);

            return $deliveredInboxes;
        }

        // Get user actor for the Announce
        $userActor = $service->getUserActor($this->post->user);
        if ($userActor === null) {
            // Create a temporary actor reference for attribution
            $userActor = ActivityPubActor::findOrCreateForUser($this->post->user);
        }

        $activity = $service->buildAnnounceActivity($groupActor, $userActor, $this->post);
        $inboxes = ActivityPubActorFollower::getUniqueInboxes($followers);

        Log::info('ActivityPub: Delivering Announce from group actor', [
            'actor' => $groupActor->actor_uri,
            'inboxes_count' => count($inboxes),
        ]);

        foreach ($inboxes as $inbox) {
            if (isset($deliveredInboxes[$inbox])) {
                continue;
            }

            DeliverToInbox::dispatch($groupActor->id, $inbox, $activity, 'multi_actor');
            $deliveredInboxes[$inbox] = true;
        }

        return $deliveredInboxes;
    }

    /**
     * Deliver Create activity from instance actor.
     *
     * @param  array<string, bool>  $deliveredInboxes
     */
    private function deliverFromInstanceActor(MultiActorActivityPubService $service, array $deliveredInboxes): void
    {
        $instanceActor = $service->getInstanceActor();
        $followers = $instanceActor->followers;

        // Also include legacy followers from the old system
        $legacyFollowers = \App\Models\ActivityPubFollower::all();

        if ($followers->isEmpty() && $legacyFollowers->isEmpty()) {
            Log::debug('ActivityPub: Instance actor has no followers');

            return;
        }

        $activity = $service->buildCreateActivity($instanceActor, $this->post);

        // Collect all inboxes
        $inboxes = [];

        // From new system
        foreach ($followers as $follower) {
            $inbox = $follower->getDeliveryInbox();
            $inboxes[$inbox] = true;
        }

        // From legacy system
        foreach ($legacyFollowers as $follower) {
            $inbox = $follower->shared_inbox_url ?? $follower->inbox_url;
            $inboxes[$inbox] = true;
        }

        Log::info('ActivityPub: Delivering Create from instance actor', [
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
