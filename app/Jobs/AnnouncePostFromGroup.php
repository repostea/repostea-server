<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ActivityPubActor;
use App\Models\ActivityPubActorFollower;
use App\Models\ActivityPubPostSettings;
use App\Models\ActivityPubSubSettings;
use App\Models\Post;
use App\Models\Sub;
use App\Services\MultiActorActivityPubService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Manually announce a post from a group/sub actor.
 *
 * Used when sub admins manually select posts to federate (when auto_announce is disabled).
 */
final class AnnouncePostFromGroup implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        private readonly Post $post,
        private readonly Sub $sub,
    ) {
        $this->onQueue(config('activitypub.queue', 'activitypub'));
    }

    public function handle(MultiActorActivityPubService $service): void
    {
        // Check if ActivityPub is enabled
        if (! config('activitypub.enabled', false)) {
            Log::debug('ActivityPub: Disabled, skipping manual announce');

            return;
        }

        // Verify post belongs to this sub
        if ($this->post->sub_id !== $this->sub->id) {
            Log::warning('ActivityPub: Post does not belong to the specified sub', [
                'post_id' => $this->post->id,
                'post_sub_id' => $this->post->sub_id,
                'target_sub_id' => $this->sub->id,
            ]);

            return;
        }

        // Check if sub has federation enabled
        $subSettings = ActivityPubSubSettings::where('sub_id', $this->sub->id)->first();
        if ($subSettings === null || ! $subSettings->federation_enabled) {
            Log::debug('ActivityPub: Sub federation not enabled', [
                'sub_id' => $this->sub->id,
            ]);

            return;
        }

        // Check if the post author allows federation
        $postSettings = ActivityPubPostSettings::where('post_id', $this->post->id)->first();
        if ($postSettings !== null && ! $postSettings->should_federate) {
            Log::debug('ActivityPub: Post author has disabled federation for this post', [
                'post_id' => $this->post->id,
            ]);

            return;
        }

        // Get the group actor
        $groupActor = $service->getGroupActor($this->sub);
        if ($groupActor === null) {
            Log::warning('ActivityPub: Could not get group actor for sub', [
                'sub_id' => $this->sub->id,
            ]);

            return;
        }

        $followers = $groupActor->followers;

        if ($followers->isEmpty()) {
            Log::debug('ActivityPub: Group actor has no followers', [
                'actor' => $groupActor->actor_uri,
            ]);

            return;
        }

        // Get or create user actor for attribution
        $userActor = $service->getUserActor($this->post->user);
        if ($userActor === null) {
            $userActor = ActivityPubActor::findOrCreateForUser($this->post->user);
        }

        $activity = $service->buildAnnounceActivity($groupActor, $userActor, $this->post);
        $inboxes = ActivityPubActorFollower::getUniqueInboxes($followers);

        Log::info('ActivityPub: Manual Announce from group actor', [
            'post_id' => $this->post->id,
            'actor' => $groupActor->actor_uri,
            'inboxes_count' => count($inboxes),
        ]);

        foreach ($inboxes as $inbox) {
            DeliverToInbox::dispatch($groupActor->id, $inbox, $activity, 'multi_actor');
        }

        // Mark post as announced (update settings if exists)
        if ($postSettings !== null && ! $postSettings->is_federated) {
            $domain = $service->getDomain();
            $postSettings->markAsFederated(
                "{$domain}/activitypub/notes/{$this->post->id}",
                "{$domain}/activitypub/activities/announce/{$this->post->id}",
            );
        }

        Log::info('ActivityPub: Manual announce complete', [
            'post_id' => $this->post->id,
            'sub_id' => $this->sub->id,
        ]);
    }
}
