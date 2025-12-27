<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityPubActor;
use App\Models\ActivityPubActorFollower;
use App\Models\ActivityPubPostSettings;
use App\Models\ActivityPubSubSettings;
use App\Models\ActivityPubUserSettings;
use App\Models\Post;
use App\Models\Sub;
use App\Models\User;
use App\Services\HttpSignatureService;
use App\Services\MultiActorActivityPubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Controller for Multi-Actor ActivityPub endpoints.
 *
 * Handles:
 * - WebFinger for users (@username) and groups (!groupname)
 * - Actor documents for users and groups
 * - Inbox for users and groups
 * - API endpoints for federation settings
 */
final class MultiActorActivityPubController extends Controller
{
    public function __construct(
        private readonly MultiActorActivityPubService $service,
        private readonly HttpSignatureService $signatureService,
    ) {}

    /**
     * Check if ActivityPub is enabled.
     */
    private function isEnabled(): bool
    {
        return (bool) config('activitypub.enabled', false);
    }

    // =========================================================================
    // WebFinger (extended)
    // =========================================================================

    /**
     * WebFinger endpoint for multi-actor discovery.
     *
     * Supports:
     * - acct:username@domain -> User or Instance actor
     * - acct:!groupname@domain -> Group actor
     *
     * GET /.well-known/webfinger?resource=acct:...
     */
    public function webfinger(Request $request): JsonResponse|Response
    {
        if (! $this->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        $resource = $request->query('resource');
        if ($resource === null || $resource === '' || ! is_string($resource)) {
            return response()->json(['error' => 'Missing resource parameter'], 400);
        }

        $actor = $this->service->resolveWebfinger($resource);

        if ($actor === null) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        return response()
            ->json($this->service->buildWebfingerResponse($actor))
            ->header('Content-Type', 'application/jrd+json; charset=utf-8')
            ->header('Access-Control-Allow-Origin', '*');
    }

    // =========================================================================
    // User Actor Endpoints
    // =========================================================================

    /**
     * User actor document.
     *
     * GET /activitypub/users/{username}
     */
    public function userActor(string $username): JsonResponse|Response
    {
        if (! $this->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        $actor = ActivityPubActor::findByUsername($username, ActivityPubActor::TYPE_USER);

        if ($actor === null) {
            return response()->json(['error' => 'Actor not found'], 404);
        }

        return response()
            ->json($actor->toActivityPub())
            ->header('Content-Type', 'application/activity+json; charset=utf-8');
    }

    /**
     * User inbox.
     *
     * POST /activitypub/users/{username}/inbox
     */
    public function userInbox(Request $request, string $username): JsonResponse|Response
    {
        if (! $this->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        $actor = ActivityPubActor::findByUsername($username, ActivityPubActor::TYPE_USER);

        if ($actor === null) {
            return response()->json(['error' => 'Actor not found'], 404);
        }

        return $this->handleInbox($request, $actor);
    }

    /**
     * User outbox.
     *
     * GET /activitypub/users/{username}/outbox
     */
    public function userOutbox(string $username): JsonResponse|Response
    {
        if (! $this->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        $actor = ActivityPubActor::findByUsername($username, ActivityPubActor::TYPE_USER);

        if ($actor === null) {
            return response()->json(['error' => 'Actor not found'], 404);
        }

        return $this->buildOutbox($actor);
    }

    /**
     * User followers.
     *
     * GET /activitypub/users/{username}/followers
     */
    public function userFollowers(string $username): JsonResponse|Response
    {
        if (! $this->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        $actor = ActivityPubActor::findByUsername($username, ActivityPubActor::TYPE_USER);

        if ($actor === null) {
            return response()->json(['error' => 'Actor not found'], 404);
        }

        return $this->buildFollowers($actor);
    }

    // =========================================================================
    // Group Actor Endpoints
    // =========================================================================

    /**
     * Group actor document.
     *
     * GET /activitypub/groups/{name}
     */
    public function groupActor(string $name): JsonResponse|Response
    {
        if (! $this->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        $actor = ActivityPubActor::findByUsername($name, ActivityPubActor::TYPE_GROUP);

        if ($actor === null) {
            return response()->json(['error' => 'Actor not found'], 404);
        }

        return response()
            ->json($actor->toActivityPub())
            ->header('Content-Type', 'application/activity+json; charset=utf-8');
    }

    /**
     * Group inbox.
     *
     * POST /activitypub/groups/{name}/inbox
     */
    public function groupInbox(Request $request, string $name): JsonResponse|Response
    {
        if (! $this->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        $actor = ActivityPubActor::findByUsername($name, ActivityPubActor::TYPE_GROUP);

        if ($actor === null) {
            return response()->json(['error' => 'Actor not found'], 404);
        }

        return $this->handleInbox($request, $actor);
    }

    /**
     * Group outbox.
     *
     * GET /activitypub/groups/{name}/outbox
     */
    public function groupOutbox(string $name): JsonResponse|Response
    {
        if (! $this->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        $actor = ActivityPubActor::findByUsername($name, ActivityPubActor::TYPE_GROUP);

        if ($actor === null) {
            return response()->json(['error' => 'Actor not found'], 404);
        }

        return $this->buildOutbox($actor);
    }

    /**
     * Group followers.
     *
     * GET /activitypub/groups/{name}/followers
     */
    public function groupFollowers(string $name): JsonResponse|Response
    {
        if (! $this->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        $actor = ActivityPubActor::findByUsername($name, ActivityPubActor::TYPE_GROUP);

        if ($actor === null) {
            return response()->json(['error' => 'Actor not found'], 404);
        }

        return $this->buildFollowers($actor);
    }

    // =========================================================================
    // Notes & Activities
    // =========================================================================

    /**
     * Note endpoint - returns a post as Note object.
     *
     * GET /activitypub/notes/{post}
     */
    public function note(Post $post): JsonResponse|Response
    {
        if (! $this->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        if ($post->status !== Post::STATUS_PUBLISHED || $post->deleted_at !== null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Check if post should be federated
        $settings = ActivityPubPostSettings::where('post_id', $post->id)->first();
        if ($settings === null || ! $settings->should_federate) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Get the user's actor
        $userActor = $this->service->getUserActor($post->user);
        if ($userActor === null) {
            // Fall back to instance actor
            $userActor = $this->service->getInstanceActor();
        }

        $activity = $this->service->buildCreateActivity($userActor, $post);

        return response()
            ->json($activity['object'])
            ->header('Content-Type', 'application/activity+json; charset=utf-8');
    }

    // =========================================================================
    // API Endpoints (for frontend)
    // =========================================================================

    /**
     * Get current user's federation settings.
     *
     * GET /api/v1/activitypub/settings
     */
    public function getSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $settings = ActivityPubUserSettings::getOrCreate($user);
        $actor = ActivityPubActor::findByUsername($user->username, ActivityPubActor::TYPE_USER);

        return response()->json([
            'federation_enabled' => $settings->federation_enabled,
            'federation_enabled_at' => $settings->federation_enabled_at?->toIso8601String(),
            'default_federate_posts' => $settings->default_federate_posts,
            'indexable' => $settings->indexable,
            'show_followers_count' => $settings->show_followers_count,
            'actor' => $actor !== null ? [
                'uri' => $actor->actor_uri,
                'handle' => $actor->getHandle(),
                'followers' => $actor->getFollowerCount(),
            ] : null,
        ]);
    }

    /**
     * Update current user's federation settings.
     *
     * PATCH /api/v1/activitypub/settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'federation_enabled' => 'sometimes|boolean',
            'default_federate_posts' => 'sometimes|boolean',
            'indexable' => 'sometimes|boolean',
            'show_followers_count' => 'sometimes|boolean',
        ]);

        $settings = ActivityPubUserSettings::getOrCreate($user);

        // Handle federation toggle
        if (isset($validated['federation_enabled'])) {
            if ($validated['federation_enabled'] && ! $settings->federation_enabled) {
                $this->service->enableUserFederation($user);
            } elseif (! $validated['federation_enabled'] && $settings->federation_enabled) {
                $settings->disableFederation();
            }
            unset($validated['federation_enabled']);
        }

        // Update other settings
        $settings->update($validated);

        return $this->getSettings($request);
    }

    /**
     * Get post federation settings.
     *
     * GET /api/v1/activitypub/posts/{post}/settings
     */
    public function getPostSettings(Post $post, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || $user->id !== $post->user_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $settings = ActivityPubPostSettings::getOrCreate($post);

        return response()->json([
            'should_federate' => $settings->should_federate,
            'is_federated' => $settings->is_federated,
            'federated_at' => $settings->federated_at?->toIso8601String(),
            'note_uri' => $settings->note_uri,
            'can_federate' => ActivityPubPostSettings::canFederate($post),
        ]);
    }

    /**
     * Update post federation settings.
     *
     * PATCH /api/v1/activitypub/posts/{post}/settings
     */
    public function updatePostSettings(Post $post, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || $user->id !== $post->user_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'should_federate' => 'required|boolean',
        ]);

        $settings = ActivityPubPostSettings::getOrCreate($post);
        $settings->update($validated);

        return $this->getPostSettings($post, $request);
    }

    /**
     * Get sub federation settings (for moderators).
     *
     * GET /api/v1/activitypub/subs/{sub}/settings
     */
    public function getSubSettings(Sub $sub, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $sub->isModerator($user)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $settings = ActivityPubSubSettings::getOrCreate($sub);
        $actor = ActivityPubActor::findByUsername($sub->name, ActivityPubActor::TYPE_GROUP);

        return response()->json([
            'federation_enabled' => $settings->federation_enabled,
            'federation_enabled_at' => $settings->federation_enabled_at?->toIso8601String(),
            'auto_announce' => $settings->auto_announce,
            'accept_remote_posts' => $settings->accept_remote_posts,
            'actor' => $actor !== null ? [
                'uri' => $actor->actor_uri,
                'handle' => $actor->getHandle(),
                'followers' => $actor->getFollowerCount(),
            ] : null,
        ]);
    }

    /**
     * Update sub federation settings (for moderators).
     *
     * PATCH /api/v1/activitypub/subs/{sub}/settings
     */
    public function updateSubSettings(Sub $sub, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $sub->isModerator($user)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'federation_enabled' => 'sometimes|boolean',
            'auto_announce' => 'sometimes|boolean',
        ]);

        $settings = ActivityPubSubSettings::getOrCreate($sub);

        // Handle federation toggle
        if (isset($validated['federation_enabled'])) {
            if ($validated['federation_enabled'] && ! $settings->federation_enabled) {
                $this->service->enableSubFederation($sub);
            } elseif (! $validated['federation_enabled'] && $settings->federation_enabled) {
                $settings->disableFederation();
            }
            unset($validated['federation_enabled']);
        }

        // Update other settings
        $settings->update($validated);

        return $this->getSubSettings($sub, $request);
    }

    /**
     * Manually announce a post from a sub/group (for moderators).
     *
     * POST /api/v1/activitypub/subs/{sub}/announce/{post}
     */
    public function announcePost(Sub $sub, Post $post, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $sub->isModerator($user)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Check if post belongs to this sub
        if ($post->sub_id !== $sub->id) {
            return response()->json(['error' => 'Post does not belong to this sub'], 400);
        }

        // Check if sub has federation enabled
        $settings = ActivityPubSubSettings::getOrCreate($sub);
        if (! $settings->federation_enabled) {
            return response()->json(['error' => 'Federation is not enabled for this sub'], 400);
        }

        // Check if post is published
        if ($post->status !== Post::STATUS_PUBLISHED) {
            return response()->json(['error' => 'Only published posts can be announced'], 400);
        }

        // Check if post author allows federation
        $postSettings = ActivityPubPostSettings::where('post_id', $post->id)->first();
        if ($postSettings !== null && ! $postSettings->should_federate) {
            return response()->json(['error' => 'Post author has disabled federation for this post'], 400);
        }

        // Dispatch the announce job
        \App\Jobs\AnnouncePostFromGroup::dispatch($post, $sub);

        return response()->json([
            'message' => 'Post announcement queued successfully',
            'post_id' => $post->id,
            'sub_id' => $sub->id,
        ]);
    }

    /**
     * Get posts in a sub that can be announced (for moderators).
     *
     * GET /api/v1/activitypub/subs/{sub}/announceable
     */
    public function getAnnounceablePosts(Sub $sub, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $sub->isModerator($user)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get published posts in this sub that haven't been federated yet
        $posts = Post::where('sub_id', $sub->id)
            ->where('status', Post::STATUS_PUBLISHED)
            ->whereDoesntHave('activityPubSettings', function ($query): void {
                $query->where('is_federated', true);
            })
            ->with(['user:id,username,name,avatar'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get(['id', 'title', 'slug', 'user_id', 'created_at']);

        return response()->json([
            'posts' => $posts,
        ]);
    }

    // =========================================================================
    // Public API Methods (for frontend display)
    // =========================================================================

    /**
     * Get public info about a user's actor.
     *
     * GET /api/v1/activitypub/users/{username}
     */
    public function getUserActorInfo(string $username): JsonResponse
    {
        $actor = ActivityPubActor::findByUsername($username, ActivityPubActor::TYPE_USER);

        if ($actor === null) {
            return response()->json(['error' => 'Actor not found'], 404);
        }

        return response()->json([
            'username' => $actor->username,
            'name' => $actor->name,
            'handle' => $actor->getHandle(),
            'uri' => $actor->actor_uri,
            'followers' => $actor->getPublicFollowerCount(),
            'icon' => $actor->icon_url,
        ]);
    }

    /**
     * Get public info about a group actor.
     *
     * GET /api/v1/activitypub/groups/{name}
     */
    public function getGroupActorInfo(string $name): JsonResponse
    {
        $actor = ActivityPubActor::findByUsername($name, ActivityPubActor::TYPE_GROUP);

        if ($actor === null) {
            return response()->json(['error' => 'Actor not found'], 404);
        }

        return response()->json([
            'name' => $actor->username,
            'display_name' => $actor->name,
            'handle' => $actor->getHandle(),
            'uri' => $actor->actor_uri,
            'followers' => $actor->getPublicFollowerCount(),
            'icon' => $actor->icon_url,
        ]);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Handle incoming activities to an inbox.
     */
    private function handleInbox(Request $request, ActivityPubActor $actor): JsonResponse
    {
        // Verify HTTP Signature
        $signatureResult = $this->signatureService->verifyRequest($request);

        if (! $signatureResult['valid']) {
            $error = $signatureResult['error'] ?? 'Unknown error';

            if ($this->signatureService->shouldLogFailures()) {
                Log::warning('ActivityPub: HTTP Signature verification failed', [
                    'actor' => $actor->actor_uri,
                    'error' => $error,
                    'keyId' => $signatureResult['keyId'] ?? 'unknown',
                    'ip' => $request->ip(),
                ]);
            }

            if ($this->signatureService->shouldEnforce()) {
                return response()->json([
                    'error' => 'Invalid HTTP Signature',
                    'message' => $error,
                ], 401);
            }
        }

        $activity = $request->json()->all();

        Log::debug('ActivityPub: Received activity for actor', [
            'actor' => $actor->actor_uri,
            'type' => $activity['type'] ?? 'unknown',
            'from' => $activity['actor'] ?? 'unknown',
            'signature_valid' => $signatureResult['valid'],
        ]);

        $type = $activity['type'] ?? null;

        switch ($type) {
            case 'Follow':
                $this->service->handleFollow($actor, $activity);
                break;

            case 'Undo':
                // First try to handle as unfollow
                if (! $this->service->handleUndo($actor, $activity)) {
                    // Then try to handle as undo like/announce
                    $this->service->handleUndoLikeOrAnnounce($activity);
                }
                break;

            case 'Delete':
                // Actor deleted their account - remove follower
                $actorId = $activity['actor'] ?? null;
                if ($actorId) {
                    ActivityPubActorFollower::where('actor_id', $actor->id)
                        ->where('follower_uri', $actorId)
                        ->delete();
                }
                break;

            case 'Like':
                $this->service->handleLike($activity);
                break;

            case 'Announce':
                $this->service->handleAnnounce($activity);
                break;

            case 'Create':
                $this->service->handleCreate($activity);
                break;

            default:
                Log::debug("ActivityPub: Ignoring activity type {$type}");
        }

        return response()->json(['status' => 'ok'], 202);
    }

    /**
     * Build outbox response for an actor.
     */
    private function buildOutbox(ActivityPubActor $actor): JsonResponse
    {
        $collection = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->outbox_uri,
            'type' => 'OrderedCollection',
            'totalItems' => 0,
            'orderedItems' => [],
        ];

        return response()
            ->json($collection)
            ->header('Content-Type', 'application/activity+json; charset=utf-8');
    }

    /**
     * Build followers response for an actor.
     */
    private function buildFollowers(ActivityPubActor $actor): JsonResponse
    {
        $collection = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->followers_uri,
            'type' => 'OrderedCollection',
            'totalItems' => $actor->getPublicFollowerCount(),
        ];

        return response()
            ->json($collection)
            ->header('Content-Type', 'application/activity+json; charset=utf-8');
    }
}
