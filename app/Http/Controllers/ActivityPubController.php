<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use const PHP_URL_HOST;

use App\Models\ActivityPubFollower;
use App\Models\Post;
use App\Services\ActivityPubService;
use App\Services\HttpSignatureService;
use App\Services\MultiActorActivityPubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Controller for ActivityPub endpoints.
 *
 * Handles WebFinger discovery, actor profile, and inbox.
 */
final class ActivityPubController extends Controller
{
    public function __construct(
        private readonly ActivityPubService $activityPub,
        private readonly MultiActorActivityPubService $multiActorService,
        private readonly HttpSignatureService $signatureService,
    ) {}

    /**
     * WebFinger endpoint for actor discovery.
     *
     * GET /.well-known/webfinger?resource=acct:username@domain
     */
    public function webfinger(Request $request): JsonResponse|Response
    {
        if (! $this->activityPub->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        $resource = $request->query('resource');
        if ($resource === null || $resource === '') {
            return response()->json(['error' => 'Missing resource parameter'], 400);
        }

        $username = config('activitypub.actor.username', 'repostea');
        $apiDomain = parse_url(config('activitypub.domain'), PHP_URL_HOST);
        $publicDomain = parse_url($this->activityPub->getPublicDomain(), PHP_URL_HOST);

        // Check if resource matches our actor (accept both API and public domain)
        $expectedResources = [
            "acct:{$username}@{$apiDomain}",
            "acct:{$username}@{$publicDomain}",
            $this->activityPub->getActorId(),
        ];

        if (! in_array($resource, $expectedResources, true)) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        return response()
            ->json($this->activityPub->buildWebfingerResponse())
            ->header('Content-Type', 'application/jrd+json; charset=utf-8')
            ->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Actor endpoint - returns the actor document.
     *
     * GET /activitypub/actor
     */
    public function actor(): JsonResponse|Response
    {
        if (! $this->activityPub->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        return response()
            ->json($this->activityPub->buildActorDocument())
            ->header('Content-Type', 'application/activity+json; charset=utf-8');
    }

    /**
     * Inbox endpoint - receives activities from remote servers.
     *
     * POST /activitypub/inbox
     */
    public function inbox(Request $request): JsonResponse|Response
    {
        if (! $this->activityPub->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        // Verify HTTP Signature
        $signatureResult = $this->signatureService->verifyRequest($request);

        if (! $signatureResult['valid']) {
            $error = $signatureResult['error'] ?? 'Unknown error';

            if ($this->signatureService->shouldLogFailures()) {
                Log::warning('ActivityPub: HTTP Signature verification failed', [
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

        Log::debug('ActivityPub: Received activity', [
            'type' => $activity['type'] ?? 'unknown',
            'actor' => $activity['actor'] ?? 'unknown',
            'signature_valid' => $signatureResult['valid'],
        ]);

        $type = $activity['type'] ?? null;

        switch ($type) {
            case 'Follow':
                $this->activityPub->handleFollow($activity);
                break;

            case 'Undo':
                if (! $this->activityPub->handleUndo($activity)) {
                    // Try to handle as undo like/announce
                    $this->multiActorService->handleUndoLikeOrAnnounce($activity);
                }
                break;

            case 'Delete':
                // Actor deleted their account - remove follower
                $actorId = $activity['actor'] ?? null;
                if ($actorId) {
                    ActivityPubFollower::where('actor_id', $actorId)->delete();
                }
                break;

            case 'Like':
                $this->multiActorService->handleLike($activity);
                break;

            case 'Announce':
                $this->multiActorService->handleAnnounce($activity);
                break;

            case 'Create':
                $this->multiActorService->handleCreate($activity);
                break;

            default:
                Log::debug("ActivityPub: Ignoring activity type {$type}");
        }

        return response()->json(['status' => 'ok'], 202);
    }

    /**
     * Outbox endpoint - lists published activities.
     *
     * GET /activitypub/outbox
     */
    public function outbox(Request $request): JsonResponse|Response
    {
        if (! $this->activityPub->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        $domain = $this->activityPub->getDomain();

        // Return an empty ordered collection (we don't implement pagination yet)
        $collection = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => "{$domain}/activitypub/outbox",
            'type' => 'OrderedCollection',
            'totalItems' => 0,
            'orderedItems' => [],
        ];

        return response()
            ->json($collection)
            ->header('Content-Type', 'application/activity+json; charset=utf-8');
    }

    /**
     * Followers endpoint - lists followers.
     *
     * GET /activitypub/followers
     */
    public function followers(Request $request): JsonResponse|Response
    {
        if (! $this->activityPub->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        $domain = $this->activityPub->getDomain();
        $count = $this->activityPub->getFollowerCount();

        // Return count only (don't expose full list for privacy)
        $collection = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => "{$domain}/activitypub/followers",
            'type' => 'OrderedCollection',
            'totalItems' => $count,
        ];

        return response()
            ->json($collection)
            ->header('Content-Type', 'application/activity+json; charset=utf-8');
    }

    /**
     * Status endpoint - returns ActivityPub status (for frontend).
     *
     * GET /api/v1/activitypub/status
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'enabled' => $this->activityPub->isEnabled(),
            'actor' => $this->activityPub->isEnabled() ? $this->activityPub->getActorId() : null,
            'username' => $this->activityPub->isEnabled() ? $this->activityPub->getUsername() : null,
            'followers' => $this->activityPub->isEnabled() ? $this->activityPub->getFollowerCount() : 0,
        ]);
    }

    /**
     * Post endpoint - returns a post as ActivityPub Note object.
     *
     * GET /activitypub/posts/{post}
     */
    public function post(Post $post): JsonResponse|Response
    {
        if (! $this->activityPub->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        if ($post->status !== Post::STATUS_PUBLISHED || $post->deleted_at !== null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $note = $this->activityPub->buildNoteObject($post);

        return response()
            ->json($note)
            ->header('Content-Type', 'application/activity+json; charset=utf-8');
    }

    /**
     * Activity endpoint - returns the Create activity for a post.
     *
     * GET /activitypub/activities/{post}
     */
    public function activity(Post $post): JsonResponse|Response
    {
        if (! $this->activityPub->isEnabled()) {
            return response('ActivityPub not enabled', 404);
        }

        if ($post->status !== 'published' || $post->deleted_at !== null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $activity = $this->activityPub->buildCreateActivity($post);

        return response()
            ->json($activity)
            ->header('Content-Type', 'application/activity+json; charset=utf-8');
    }
}
