<?php

declare(strict_types=1);

namespace App\Services;

use const ENT_QUOTES;
use const PHP_URL_HOST;

use App\Models\ActivityPubActor;
use App\Models\ActivityPubActorFollower;
use App\Models\ActivityPubActorKey;
use App\Models\ActivityPubSubSettings;
use App\Models\ActivityPubUserSettings;
use App\Models\Comment;
use App\Models\Post;
use App\Models\RemoteUser;
use App\Models\Sub;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Multi-Actor ActivityPub Service.
 *
 * Handles ActivityPub operations for multiple actor types:
 * - Instance actor (Application): @repostea@domain
 * - User actors (Person): @username@domain
 * - Group actors (Group): !groupname@domain
 *
 * Implements FEP-1b12 for group federation.
 */
final class MultiActorActivityPubService
{
    public function __construct(
        private readonly UrlValidationService $urlValidator,
    ) {}

    /**
     * Get the base domain.
     */
    public function getDomain(): string
    {
        return rtrim((string) config('activitypub.domain'), '/');
    }

    /**
     * Get the public domain (for handles).
     */
    public function getPublicDomain(): string
    {
        return rtrim((string) config('activitypub.public_domain', $this->getDomain()), '/');
    }

    /**
     * Get the public host (for WebFinger).
     */
    public function getPublicHost(): string
    {
        return (string) parse_url($this->getPublicDomain(), PHP_URL_HOST);
    }

    // =========================================================================
    // Actor Management
    // =========================================================================

    /**
     * Get or create the instance actor.
     */
    public function getInstanceActor(): ActivityPubActor
    {
        $actor = ActivityPubActor::findOrCreateInstanceActor();
        ActivityPubActorKey::ensureForActor($actor);

        return $actor;
    }

    /**
     * Get or create a user's actor (if they have federation enabled).
     */
    public function getUserActor(User $user): ?ActivityPubActor
    {
        $settings = ActivityPubUserSettings::where('user_id', $user->id)->first();

        if ($settings === null || ! $settings->federation_enabled) {
            return null;
        }

        $actor = ActivityPubActor::findOrCreateForUser($user);
        ActivityPubActorKey::ensureForActor($actor);

        return $actor;
    }

    /**
     * Get or create a sub's group actor (if federation is enabled).
     */
    public function getGroupActor(Sub $sub): ?ActivityPubActor
    {
        $settings = ActivityPubSubSettings::where('sub_id', $sub->id)->first();

        if ($settings === null || ! $settings->federation_enabled) {
            return null;
        }

        $actor = ActivityPubActor::findOrCreateForSub($sub);
        ActivityPubActorKey::ensureForActor($actor);

        return $actor;
    }

    /**
     * Enable federation for a user.
     */
    public function enableUserFederation(User $user): ActivityPubActor
    {
        $settings = ActivityPubUserSettings::getOrCreate($user);
        $settings->enableFederation();

        $actor = ActivityPubActor::findOrCreateForUser($user);
        ActivityPubActorKey::ensureForActor($actor);

        Log::info("ActivityPub: Enabled federation for user {$user->username}");

        return $actor;
    }

    /**
     * Enable federation for a sub.
     */
    public function enableSubFederation(Sub $sub): ActivityPubActor
    {
        $settings = ActivityPubSubSettings::getOrCreate($sub);
        $settings->enableFederation();

        $actor = ActivityPubActor::findOrCreateForSub($sub);
        ActivityPubActorKey::ensureForActor($actor);

        Log::info("ActivityPub: Enabled federation for sub {$sub->name}");

        return $actor;
    }

    // =========================================================================
    // WebFinger
    // =========================================================================

    /**
     * Build WebFinger response for an actor.
     *
     * @return array<string, mixed>
     */
    public function buildWebfingerResponse(ActivityPubActor $actor): array
    {
        $host = $this->getPublicHost();
        $prefix = $actor->isGroup() ? '!' : '';

        return [
            'subject' => "acct:{$prefix}{$actor->username}@{$host}",
            'aliases' => [
                $actor->actor_uri,
            ],
            'links' => [
                [
                    'rel' => 'self',
                    'type' => 'application/activity+json',
                    'href' => $actor->actor_uri,
                ],
                [
                    'rel' => 'http://webfinger.net/rel/profile-page',
                    'type' => 'text/html',
                    'href' => $this->getProfileUrl($actor),
                ],
            ],
        ];
    }

    /**
     * Get the profile URL for an actor.
     */
    private function getProfileUrl(ActivityPubActor $actor): string
    {
        $clientUrl = rtrim((string) config('app.client_url', $this->getPublicDomain()), '/');

        return match ($actor->actor_type) {
            ActivityPubActor::TYPE_INSTANCE => $clientUrl,
            ActivityPubActor::TYPE_USER => "{$clientUrl}/u/{$actor->username}",
            ActivityPubActor::TYPE_GROUP => "{$clientUrl}/r/{$actor->username}",
            default => $clientUrl,
        };
    }

    /**
     * Resolve a WebFinger resource to an actor.
     *
     * Supports:
     * - acct:username@domain (user)
     * - acct:!groupname@domain (group)
     */
    public function resolveWebfinger(string $resource): ?ActivityPubActor
    {
        // Parse the resource
        if (! str_starts_with($resource, 'acct:')) {
            return null;
        }

        $resource = substr($resource, 5); // Remove "acct:"

        // Check for group prefix (!)
        $isGroup = str_starts_with($resource, '!');
        if ($isGroup) {
            $resource = substr($resource, 1); // Remove "!"
        }

        // Extract username and domain
        $parts = explode('@', $resource);
        if (count($parts) !== 2) {
            return null;
        }

        [$username, $domain] = $parts;

        // Verify domain matches ours
        if ($domain !== $this->getPublicHost()) {
            return null;
        }

        // Look up the actor
        if ($isGroup) {
            return ActivityPubActor::findByUsername($username, ActivityPubActor::TYPE_GROUP);
        }

        // Check instance actor first
        $instanceUsername = config('activitypub.actor.username', 'repostea');
        if ($username === $instanceUsername) {
            return $this->getInstanceActor();
        }

        // Then check user actors
        return ActivityPubActor::findByUsername($username, ActivityPubActor::TYPE_USER);
    }

    // =========================================================================
    // HTTP Signatures
    // =========================================================================

    /**
     * Sign an HTTP request for an actor.
     *
     * @param  array<string, string>  $headers
     *
     * @return array<string, string>
     */
    public function signRequest(
        ActivityPubActor $actor,
        string $method,
        string $url,
        array $headers,
        ?string $body = null,
    ): array {
        $keys = $actor->keys;
        if ($keys === null) {
            throw new InvalidArgumentException("Actor {$actor->actor_uri} has no keys");
        }

        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        if (isset($parsed['query'])) {
            $path .= '?' . $parsed['query'];
        }

        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $host = $parsed['host'];

        $headers['Host'] = $host;
        $headers['Date'] = $date;

        if ($body !== null) {
            $digest = 'SHA-256=' . base64_encode(hash('sha256', $body, true));
            $headers['Digest'] = $digest;
        }

        // Build signature string
        $signedHeaders = ['(request-target)', 'host', 'date'];
        if ($body !== null) {
            $signedHeaders[] = 'digest';
        }

        $signatureString = '';
        foreach ($signedHeaders as $header) {
            if ($header === '(request-target)') {
                $signatureString .= '(request-target): ' . strtolower($method) . " {$path}\n";
            } else {
                $signatureString .= strtolower($header) . ': ' . $headers[ucfirst($header)] . "\n";
            }
        }
        $signatureString = rtrim($signatureString, "\n");

        // Sign with RSA-SHA256
        $privateKey = $keys->getPrivateKeyForSigning();
        $signature = base64_encode($privateKey->sign($signatureString));

        $headers['Signature'] = sprintf(
            'keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
            $keys->key_id,
            implode(' ', $signedHeaders),
            $signature,
        );

        return $headers;
    }

    /**
     * Send an activity to a remote inbox.
     *
     * @param  array<string, mixed>  $activity
     */
    public function sendToInbox(ActivityPubActor $actor, string $inboxUrl, array $activity): bool
    {
        // Validate inbox URL to prevent SSRF
        try {
            $this->urlValidator->validate($inboxUrl);
        } catch (InvalidArgumentException $e) {
            Log::warning("ActivityPub: Invalid inbox URL rejected: {$inboxUrl}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        // Check if the target instance is blocked
        $parsed = parse_url($inboxUrl);
        $targetDomain = $parsed['host'] ?? null;
        if ($targetDomain !== null && \App\Models\ActivityPubBlockedInstance::isBlocked($targetDomain)) {
            Log::debug("ActivityPub: Skipping delivery to blocked instance: {$targetDomain}");

            return false;
        }

        $body = json_encode($activity);

        $headers = $this->signRequest($actor, 'POST', $inboxUrl, [
            'Content-Type' => 'application/activity+json',
            'Accept' => 'application/activity+json',
        ], $body);

        try {
            $response = Http::withHeaders($headers)
                ->timeout((int) config('activitypub.http.timeout', 10))
                ->retry(
                    (int) config('activitypub.http.retries', 3),
                    (int) config('activitypub.http.retry_delay', 5) * 1000,
                )
                ->withBody($body, 'application/activity+json')
                ->post($inboxUrl);

            if ($response->successful() || $response->status() === 202) {
                Log::debug("ActivityPub: Delivered to {$inboxUrl}", [
                    'actor' => $actor->actor_uri,
                    'status' => $response->status(),
                ]);

                return true;
            }

            Log::warning("ActivityPub: Failed to deliver to {$inboxUrl}", [
                'actor' => $actor->actor_uri,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return false;
        } catch (Exception $e) {
            Log::error("ActivityPub: Exception delivering to {$inboxUrl}", [
                'actor' => $actor->actor_uri,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // =========================================================================
    // Follow Handling
    // =========================================================================

    /**
     * Handle an incoming Follow activity.
     *
     * @param  array<string, mixed>  $activity
     */
    public function handleFollow(ActivityPubActor $actor, array $activity): bool
    {
        $followerUri = $activity['actor'] ?? null;
        if (! is_string($followerUri) || $followerUri === '') {
            return false;
        }

        // Fetch remote actor info
        $remoteActor = $this->fetchRemoteActor($followerUri);
        if ($remoteActor === null) {
            Log::warning("ActivityPub: Could not fetch actor {$followerUri}");

            return false;
        }

        // Store follower
        ActivityPubActorFollower::createFromRemoteActor($actor, $followerUri, $remoteActor);

        Log::info("ActivityPub: New follower for {$actor->actor_uri}: {$followerUri}");

        // Auto-accept
        if ((bool) config('activitypub.auto_accept_follows', true)) {
            $this->sendAccept($actor, $activity, $remoteActor);
        }

        return true;
    }

    /**
     * Handle an incoming Undo activity (unfollow).
     *
     * @param  array<string, mixed>  $activity
     */
    public function handleUndo(ActivityPubActor $actor, array $activity): bool
    {
        $object = $activity['object'] ?? null;

        if (is_array($object) && ($object['type'] ?? '') === 'Follow') {
            $followerUri = $activity['actor'] ?? null;
            if (is_string($followerUri) && $followerUri !== '') {
                ActivityPubActorFollower::where('actor_id', $actor->id)
                    ->where('follower_uri', $followerUri)
                    ->delete();

                Log::info("ActivityPub: Unfollowed {$actor->actor_uri} by {$followerUri}");

                return true;
            }
        }

        return false;
    }

    /**
     * Send Accept activity for a Follow.
     *
     * @param  array<string, mixed>  $followActivity
     * @param  array<string, mixed>  $remoteActor
     */
    public function sendAccept(ActivityPubActor $actor, array $followActivity, array $remoteActor): void
    {
        $accept = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->actor_uri . '#accepts/' . Str::uuid(),
            'type' => 'Accept',
            'actor' => $actor->actor_uri,
            'object' => $followActivity,
        ];

        $inbox = $remoteActor['inbox'] ?? "{$followActivity['actor']}/inbox";
        $this->sendToInbox($actor, $inbox, $accept);
    }

    /**
     * Fetch a remote actor's info.
     *
     * @return array<string, mixed>|null
     */
    public function fetchRemoteActor(string $actorUri): ?array
    {
        try {
            $this->urlValidator->validate($actorUri);
        } catch (InvalidArgumentException $e) {
            Log::warning("ActivityPub: Invalid actor URI rejected: {$actorUri}");

            return null;
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/activity+json, application/ld+json',
            ])
                ->timeout(10)
                ->get($actorUri);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (Exception $e) {
            Log::warning("ActivityPub: Failed to fetch actor {$actorUri}", [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    // =========================================================================
    // Activity Building
    // =========================================================================

    /**
     * Build a Create activity for a post (from user actor).
     *
     * @return array<string, mixed>
     */
    public function buildCreateActivity(ActivityPubActor $actor, Post $post): array
    {
        $domain = $this->getDomain();
        $clientUrl = rtrim((string) config('app.client_url', $this->getPublicDomain()), '/');
        $postUrl = "{$clientUrl}/posts/{$post->slug}";

        $noteId = "{$domain}/activitypub/notes/{$post->id}";
        $activityId = "{$domain}/activitypub/activities/create/{$post->id}";

        $note = $this->buildNote($actor, $post, $noteId, $postUrl);

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Create',
            'actor' => $actor->actor_uri,
            'published' => $post->published_at?->toIso8601String() ?? $post->created_at->toIso8601String(),
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$actor->followers_uri],
            'object' => $note,
        ];
    }

    /**
     * Build an Announce activity for a post (from group actor).
     * This is the FEP-1b12 group federation mechanism.
     *
     * @return array<string, mixed>
     */
    public function buildAnnounceActivity(ActivityPubActor $groupActor, ActivityPubActor $userActor, Post $post): array
    {
        $domain = $this->getDomain();
        $activityId = "{$domain}/activitypub/activities/announce/{$post->id}";

        // The object is the Create activity from the user
        $createActivity = $this->buildCreateActivity($userActor, $post);

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Announce',
            'actor' => $groupActor->actor_uri,
            'published' => $post->published_at?->toIso8601String() ?? $post->created_at->toIso8601String(),
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$groupActor->followers_uri],
            'object' => $createActivity,
            // FEP-1b12: Include audience for forum context
            'audience' => $groupActor->actor_uri,
        ];
    }

    /**
     * Build a Note object for a post.
     *
     * @return array<string, mixed>
     */
    private function buildNote(ActivityPubActor $actor, Post $post, string $noteId, string $postUrl): array
    {
        $content = $this->formatPostContent($post);

        $note = [
            'id' => $noteId,
            'type' => 'Note',
            'published' => $post->published_at?->toIso8601String() ?? $post->created_at->toIso8601String(),
            'attributedTo' => $actor->actor_uri,
            'content' => $content,
            'url' => $postUrl,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$actor->followers_uri],
        ];

        // Add audience if post is in a sub (FEP-1b12)
        if ($post->sub !== null) {
            $groupActor = ActivityPubActor::findByUsername($post->sub->name, ActivityPubActor::TYPE_GROUP);
            if ($groupActor !== null) {
                $note['audience'] = $groupActor->actor_uri;
            }
        }

        // Add image attachment if post has thumbnail
        if ($post->thumbnail_url !== null && $post->thumbnail_url !== '') {
            $note['attachment'] = [
                [
                    'type' => 'Image',
                    'mediaType' => 'image/jpeg',
                    'url' => $post->thumbnail_url,
                    'name' => $post->title,
                ],
            ];
        }

        return $note;
    }

    /**
     * Format post content for ActivityPub (HTML).
     */
    private function formatPostContent(Post $post): string
    {
        $title = htmlspecialchars($post->title, ENT_QUOTES, 'UTF-8');
        $clientUrl = rtrim((string) config('app.client_url', $this->getPublicDomain()), '/');
        $postUrl = "{$clientUrl}/posts/{$post->slug}";

        $content = "<p><strong>{$title}</strong></p>";

        if ($post->content !== null && $post->content !== '') {
            $postContent = htmlspecialchars(Str::limit($post->content, 500), ENT_QUOTES, 'UTF-8');
            $content .= "<p>{$postContent}</p>";
        }

        $content .= "<p><a href=\"{$postUrl}\">{$postUrl}</a></p>";

        return $content;
    }

    /**
     * Build an Update activity for an edited post.
     *
     * @return array<string, mixed>
     */
    public function buildUpdateActivity(ActivityPubActor $actor, Post $post): array
    {
        $domain = $this->getDomain();
        $clientUrl = rtrim((string) config('app.client_url', $this->getPublicDomain()), '/');
        $postUrl = "{$clientUrl}/posts/{$post->slug}";

        $noteId = "{$domain}/activitypub/notes/{$post->id}";
        $activityId = "{$domain}/activitypub/activities/update/{$post->id}-" . time();

        $note = $this->buildNote($actor, $post, $noteId, $postUrl);

        // Add updated timestamp to the note
        $note['updated'] = $post->updated_at->toIso8601String();

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Update',
            'actor' => $actor->actor_uri,
            'published' => $post->updated_at->toIso8601String(),
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$actor->followers_uri],
            'object' => $note,
        ];
    }

    /**
     * Build a Delete activity for a post.
     *
     * @return array<string, mixed>
     */
    public function buildDeleteActivity(ActivityPubActor $actor, int $postId): array
    {
        $domain = $this->getDomain();
        $noteId = "{$domain}/activitypub/notes/{$postId}";
        $activityId = "{$domain}/activitypub/activities/delete/{$postId}-" . time();

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Delete',
            'actor' => $actor->actor_uri,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'object' => [
                'id' => $noteId,
                'type' => 'Tombstone',
            ],
        ];
    }

    // =========================================================================
    // Inbound Activity Handlers
    // =========================================================================

    /**
     * Handle an incoming Like activity.
     *
     * @param  array<string, mixed>  $activity
     */
    public function handleLike(array $activity): bool
    {
        $actorUri = $activity['actor'] ?? null;
        $object = $activity['object'] ?? null;

        if (! is_string($actorUri) || $actorUri === '') {
            return false;
        }

        // Get the object URI (what was liked)
        $objectUri = is_string($object) ? $object : ($object['id'] ?? null);
        if (! is_string($objectUri) || $objectUri === '') {
            return false;
        }

        // Try to find a local post from the object URI
        $post = $this->findPostFromUri($objectUri);
        if ($post === null) {
            Log::debug("ActivityPub: Like for unknown object: {$objectUri}");

            return false;
        }

        // Increment federation likes count
        $post->increment('federation_likes_count');

        Log::info("ActivityPub: Like received for post {$post->id} from {$actorUri}");

        return true;
    }

    /**
     * Handle an incoming Announce (boost/share) activity.
     *
     * @param  array<string, mixed>  $activity
     */
    public function handleAnnounce(array $activity): bool
    {
        $actorUri = $activity['actor'] ?? null;
        $object = $activity['object'] ?? null;

        if (! is_string($actorUri) || $actorUri === '') {
            return false;
        }

        // Get the object URI (what was announced)
        $objectUri = is_string($object) ? $object : ($object['id'] ?? null);
        if (! is_string($objectUri) || $objectUri === '') {
            return false;
        }

        // Try to find a local post from the object URI
        $post = $this->findPostFromUri($objectUri);
        if ($post === null) {
            Log::debug("ActivityPub: Announce for unknown object: {$objectUri}");

            return false;
        }

        // Increment federation shares count
        $post->increment('federation_shares_count');

        Log::info("ActivityPub: Announce received for post {$post->id} from {$actorUri}");

        return true;
    }

    /**
     * Handle an incoming Create activity (for comments/replies).
     *
     * @param  array<string, mixed>  $activity
     */
    public function handleCreate(array $activity): bool
    {
        $actorUri = $activity['actor'] ?? null;
        $object = $activity['object'] ?? null;

        if (! is_string($actorUri) || $actorUri === '') {
            return false;
        }

        if (! is_array($object)) {
            return false;
        }

        $objectType = $object['type'] ?? '';
        if ($objectType !== 'Note') {
            Log::debug("ActivityPub: Create for non-Note type: {$objectType}");

            return false;
        }

        // Check if this is a reply to one of our posts
        $inReplyTo = $object['inReplyTo'] ?? null;
        if (! is_string($inReplyTo) || $inReplyTo === '') {
            Log::debug('ActivityPub: Create(Note) without inReplyTo, ignoring');

            return false;
        }

        // Try to find the parent post
        $post = $this->findPostFromUri($inReplyTo);
        if ($post === null) {
            Log::debug("ActivityPub: Create(Note) reply to unknown object: {$inReplyTo}");

            return false;
        }

        // Fetch the remote actor
        $remoteActorData = $this->fetchRemoteActor($actorUri);
        if ($remoteActorData === null) {
            Log::warning("ActivityPub: Could not fetch actor for Create: {$actorUri}");

            return false;
        }

        // Create or get remote user
        $remoteUser = RemoteUser::findOrCreateFromActor([
            'id' => $actorUri,
            'preferredUsername' => $remoteActorData['preferredUsername'] ?? 'unknown',
            'name' => $remoteActorData['name'] ?? null,
            'icon' => $remoteActorData['icon'] ?? null,
            'url' => $remoteActorData['url'] ?? $actorUri,
            'software' => $this->detectSoftware($remoteActorData),
        ]);

        // Extract comment content
        $content = $this->extractTextContent($object['content'] ?? '');
        if ($content === '') {
            Log::debug('ActivityPub: Create(Note) with empty content');

            return false;
        }

        // Create the comment
        $comment = Comment::create([
            'content' => $content,
            'user_id' => null,
            'remote_user_id' => $remoteUser->id,
            'post_id' => $post->id,
            'parent_id' => null,
            'votes_count' => 0,
            'is_anonymous' => false,
            'status' => 'published',
            'source' => $remoteUser->software ?? 'fediverse',
            'source_uri' => $object['id'] ?? $actorUri,
        ]);

        // Increment federation replies count
        $post->increment('federation_replies_count');

        Log::info("ActivityPub: Comment created for post {$post->id} from {$actorUri} (comment ID: {$comment->id})");

        return true;
    }

    /**
     * Handle an incoming Undo activity for Like or Announce.
     *
     * @param  array<string, mixed>  $activity
     */
    public function handleUndoLikeOrAnnounce(array $activity): bool
    {
        $object = $activity['object'] ?? null;

        if (! is_array($object)) {
            return false;
        }

        $objectType = $object['type'] ?? '';
        $targetUri = is_string($object['object'] ?? null)
            ? $object['object']
            : ($object['object']['id'] ?? null);

        if (! is_string($targetUri) || $targetUri === '') {
            return false;
        }

        $post = $this->findPostFromUri($targetUri);
        if ($post === null) {
            return false;
        }

        if ($objectType === 'Like' && $post->federation_likes_count > 0) {
            $post->decrement('federation_likes_count');
            Log::info("ActivityPub: Undo Like for post {$post->id}");

            return true;
        }

        if ($objectType === 'Announce' && $post->federation_shares_count > 0) {
            $post->decrement('federation_shares_count');
            Log::info("ActivityPub: Undo Announce for post {$post->id}");

            return true;
        }

        return false;
    }

    /**
     * Find a local post from an ActivityPub URI.
     */
    private function findPostFromUri(string $uri): ?Post
    {
        $domain = $this->getDomain();
        $clientUrl = rtrim((string) config('app.client_url', $this->getPublicDomain()), '/');

        // Match /activitypub/notes/{id} format
        if (preg_match('#/activitypub/notes/(\d+)$#', $uri, $matches)) {
            return Post::find((int) $matches[1]);
        }

        // Match /post/{slug} or /posts/{slug} format
        if (preg_match('#/posts?/([^/]+)$#', $uri, $matches)) {
            return Post::where('slug', $matches[1])->first();
        }

        // Match note ID with activity suffix (e.g., /activitypub/notes/123#create)
        if (preg_match('#/activitypub/notes/(\d+)#', $uri, $matches)) {
            return Post::find((int) $matches[1]);
        }

        return null;
    }

    /**
     * Detect the software type from actor data.
     */
    private function detectSoftware(array $actorData): ?string
    {
        // Check endpoints for software hints
        $endpoints = $actorData['endpoints'] ?? [];
        $inbox = $actorData['inbox'] ?? '';

        if (str_contains($inbox, 'mastodon')) {
            return 'mastodon';
        }
        if (str_contains($inbox, 'lemmy')) {
            return 'lemmy';
        }
        if (str_contains($inbox, 'pleroma') || str_contains($inbox, 'akkoma')) {
            return 'pleroma';
        }
        if (str_contains($inbox, 'misskey') || str_contains($inbox, 'calckey') || str_contains($inbox, 'firefish')) {
            return 'misskey';
        }
        if (str_contains($inbox, 'kbin') || str_contains($inbox, 'mbin')) {
            return 'kbin';
        }
        if (str_contains($inbox, 'pixelfed')) {
            return 'pixelfed';
        }
        if (str_contains($inbox, 'peertube')) {
            return 'peertube';
        }
        if (str_contains($inbox, 'friendica')) {
            return 'friendica';
        }

        return null;
    }

    /**
     * Extract plain text from HTML content.
     */
    private function extractTextContent(string $html): string
    {
        // Remove HTML tags but preserve line breaks
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? '';
        $text = preg_replace('/<\/p>/i', "\n\n", $text) ?? '';
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = trim($text);

        // Limit length
        if (mb_strlen($text) > 10000) {
            $text = mb_substr($text, 0, 10000) . '...';
        }

        return $text;
    }
}
