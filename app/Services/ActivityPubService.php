<?php

declare(strict_types=1);

namespace App\Services;

use const ENT_QUOTES;
use const PHP_URL_HOST;

use App\Models\ActivityPubActor;
use App\Models\ActivityPubActorFollower;
use App\Models\ActivityPubActorKey;
use App\Models\ActivityPubFollower;
use App\Models\Post;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * ActivityPub service for federation.
 *
 * Handles outbound publishing of posts to Fediverse followers.
 */
final class ActivityPubService
{
    private ?ActivityPubActor $instanceActor = null;

    public function __construct(
        private readonly UrlValidationService $urlValidator,
    ) {}

    /**
     * Check if ActivityPub is enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) config('activitypub.enabled', false);
    }

    /**
     * Get the actor ID (URI).
     */
    public function getActorId(): string
    {
        $domain = $this->getDomain();
        $username = config('activitypub.actor.username', 'repostea');

        return "{$domain}/activitypub/actor";
    }

    /**
     * Get the domain without trailing slash.
     */
    public function getDomain(): string
    {
        return rtrim((string) config('activitypub.domain'), '/');
    }

    /**
     * Get the actor username.
     */
    public function getUsername(): string
    {
        return (string) config('activitypub.actor.username', 'repostea');
    }

    /**
     * Get the instance actor model.
     */
    public function getInstanceActor(): ActivityPubActor
    {
        if ($this->instanceActor === null) {
            $this->instanceActor = ActivityPubActor::findOrCreateInstanceActor();
            // Ensure keys exist for the instance actor
            ActivityPubActorKey::ensureForActor($this->instanceActor);
            // Refresh to load the keys relationship
            $this->instanceActor->refresh();
        }

        return $this->instanceActor;
    }

    /**
     * Get the RSA key pair from the instance actor.
     *
     * @return array{private: string, public: string}
     */
    public function getKeyPair(): array
    {
        $actor = $this->getInstanceActor();
        $keys = $actor->keys;

        if ($keys === null) {
            throw new RuntimeException('Instance actor has no keys');
        }

        return [
            'private' => $keys->private_key,
            'public' => $keys->public_key,
        ];
    }

    /**
     * Build the actor document (JSON-LD).
     *
     * @return array<string, mixed>
     */
    public function buildActorDocument(): array
    {
        $instanceActor = $this->getInstanceActor();
        $keys = $instanceActor->keys;

        if ($keys === null) {
            throw new RuntimeException('Instance actor has no keys');
        }

        $actorId = $instanceActor->actor_uri;
        $domain = $this->getDomain();

        $actor = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
            'id' => $actorId,
            'type' => $instanceActor->activitypub_type,
            'preferredUsername' => $instanceActor->preferred_username,
            'name' => $instanceActor->name ?? config('activitypub.actor.name', 'Repostea'),
            'summary' => $instanceActor->summary ?? config('activitypub.actor.summary', ''),
            'inbox' => $instanceActor->inbox_uri,
            'outbox' => $instanceActor->outbox_uri,
            'followers' => $instanceActor->followers_uri,
            'url' => config('app.client_url', $domain),
            'publicKey' => [
                'id' => $keys->key_id,
                'owner' => $actorId,
                'publicKeyPem' => $keys->public_key,
            ],
        ];

        $icon = $instanceActor->icon_url ?? config('activitypub.actor.icon');
        if (is_string($icon) && $icon !== '') {
            $actor['icon'] = [
                'type' => 'Image',
                'mediaType' => 'image/png',
                'url' => $icon,
            ];
        }

        return $actor;
    }

    /**
     * Get the public domain for WebFinger (user-facing handle).
     */
    public function getPublicDomain(): string
    {
        return rtrim((string) config('activitypub.public_domain', $this->getDomain()), '/');
    }

    /**
     * Build WebFinger response.
     *
     * @return array<string, mixed>
     */
    public function buildWebfingerResponse(): array
    {
        $domain = $this->getDomain();
        $publicDomain = $this->getPublicDomain();
        $username = $this->getUsername();
        $publicHost = parse_url($publicDomain, PHP_URL_HOST);

        return [
            'subject' => "acct:{$username}@{$publicHost}",
            'aliases' => [
                $this->getActorId(),
            ],
            'links' => [
                [
                    'rel' => 'self',
                    'type' => 'application/activity+json',
                    'href' => $this->getActorId(),
                ],
                [
                    'rel' => 'http://webfinger.net/rel/profile-page',
                    'type' => 'text/html',
                    'href' => $publicDomain,
                ],
            ],
        ];
    }

    /**
     * Sign an HTTP request with HTTP Signatures.
     *
     * @param  array<string, string>  $headers
     *
     * @return array<string, string>
     */
    public function signRequest(string $method, string $url, array $headers, ?string $body = null): array
    {
        $actor = $this->getInstanceActor();
        $keys = $actor->keys;

        if ($keys === null) {
            throw new RuntimeException('Instance actor has no keys');
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

        // Sign with RSA-SHA256 using the actor's key
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
     */
    public function sendToInbox(string $inboxUrl, array $activity): bool
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

        $body = json_encode($activity);

        $headers = $this->signRequest('POST', $inboxUrl, [
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
                    'status' => $response->status(),
                    'response' => mb_substr($response->body(), 0, 500),
                ]);

                return true;
            }

            Log::warning("ActivityPub: Failed to deliver to {$inboxUrl}", [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1000),
            ]);

            return false;
        } catch (Exception $e) {
            Log::error("ActivityPub: Exception delivering to {$inboxUrl}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Handle an incoming Follow activity.
     *
     * @param  array<string, mixed>  $activity
     */
    public function handleFollow(array $activity): bool
    {
        $actorId = $activity['actor'] ?? null;
        if (! is_string($actorId) || $actorId === '') {
            return false;
        }

        // Check if the Follow is targeting a specific actor (user, group, etc.)
        $objectUri = $activity['object'] ?? null;
        if (is_string($objectUri) && $objectUri !== '') {
            // Try to find a local actor matching this URI
            $targetActor = ActivityPubActor::where('actor_uri', $objectUri)->first();
            if ($targetActor !== null) {
                // This is a follow for a specific actor, use the new system
                return $this->handleFollowForActor($targetActor, $actorId, $activity);
            }
        }

        // Fetch remote actor info
        $actorInfo = $this->fetchRemoteActor($actorId);
        if ($actorInfo === null) {
            Log::warning("ActivityPub: Could not fetch actor {$actorId}");

            return false;
        }

        // Extract domain from actor ID
        $parsed = parse_url($actorId);
        $domain = $parsed['host'] ?? 'unknown';

        // Store follower in legacy system (for instance follows)
        ActivityPubFollower::updateOrCreate(
            ['actor_id' => $actorId],
            [
                'inbox_url' => $actorInfo['inbox'] ?? "{$actorId}/inbox",
                'shared_inbox_url' => $actorInfo['endpoints']['sharedInbox'] ?? ($actorInfo['sharedInbox'] ?? null),
                'username' => $actorInfo['preferredUsername'] ?? null,
                'domain' => $domain,
                'display_name' => $actorInfo['name'] ?? null,
                'avatar_url' => $actorInfo['icon']['url'] ?? null,
                'followed_at' => now(),
            ],
        );

        Log::info("ActivityPub: New follower {$actorId}");

        // Auto-accept if configured
        if ((bool) config('activitypub.auto_accept_follows', true)) {
            $this->sendAccept($activity, $actorInfo);
        }

        return true;
    }

    /**
     * Handle a Follow activity for a specific local actor.
     *
     * @param  array<string, mixed>  $activity
     */
    private function handleFollowForActor(ActivityPubActor $targetActor, string $followerActorUri, array $activity): bool
    {
        // Fetch remote actor info
        $actorInfo = $this->fetchRemoteActor($followerActorUri);
        if ($actorInfo === null) {
            Log::warning("ActivityPub: Could not fetch actor {$followerActorUri}");

            return false;
        }

        // Store follower in new multi-actor system using the model's helper method
        ActivityPubActorFollower::createFromRemoteActor($targetActor, $followerActorUri, $actorInfo);

        Log::info("ActivityPub: New follower for {$targetActor->actor_uri}: {$followerActorUri}");

        // Auto-accept if configured
        if ((bool) config('activitypub.auto_accept_follows', true)) {
            $this->sendAcceptForActor($targetActor, $activity, $actorInfo);
        }

        return true;
    }

    /**
     * Send Accept activity for a Follow to a specific actor.
     *
     * @param  array<string, mixed>  $followActivity
     * @param  array<string, mixed>  $remoteActor
     */
    private function sendAcceptForActor(ActivityPubActor $actor, array $followActivity, array $remoteActor): void
    {
        $accept = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->actor_uri . '#accepts/' . Str::uuid(),
            'type' => 'Accept',
            'actor' => $actor->actor_uri,
            'object' => $followActivity,
        ];

        $inbox = $remoteActor['inbox'] ?? "{$followActivity['actor']}/inbox";

        // Use actor-specific signing via MultiActorActivityPubService
        $multiActorService = app(MultiActorActivityPubService::class);
        $multiActorService->sendToInbox($actor, $inbox, $accept);
    }

    /**
     * Handle an incoming Undo activity (for unfollows).
     *
     * @param  array<string, mixed>  $activity
     */
    public function handleUndo(array $activity): bool
    {
        $object = $activity['object'] ?? null;

        // Check if it's undoing a Follow
        if (is_array($object) && ($object['type'] ?? '') === 'Follow') {
            $actorId = $activity['actor'] ?? null;
            if (! is_string($actorId) || $actorId === '') {
                return false;
            }

            // Check if the Follow targeted a specific local actor
            $followObject = $object['object'] ?? null;
            if (is_string($followObject) && $followObject !== '') {
                $targetActor = ActivityPubActor::where('actor_uri', $followObject)->first();
                if ($targetActor !== null) {
                    // Remove from new multi-actor system
                    ActivityPubActorFollower::where('actor_id', $targetActor->id)
                        ->where('follower_uri', $actorId)
                        ->delete();
                    Log::info("ActivityPub: {$actorId} unfollowed {$targetActor->actor_uri}");

                    return true;
                }
            }

            // Fall back to legacy system
            ActivityPubFollower::where('actor_id', $actorId)->delete();
            Log::info("ActivityPub: Unfollowed by {$actorId}");

            return true;
        }

        return false;
    }

    /**
     * Send Accept activity for a Follow.
     *
     * @param  array<string, mixed>  $followActivity
     * @param  array<string, mixed>  $remoteActor
     */
    public function sendAccept(array $followActivity, array $remoteActor): void
    {
        $accept = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $this->getActorId() . '#accepts/' . Str::uuid(),
            'type' => 'Accept',
            'actor' => $this->getActorId(),
            'object' => $followActivity,
        ];

        $inbox = $remoteActor['inbox'] ?? "{$followActivity['actor']}/inbox";
        $this->sendToInbox($inbox, $accept);
    }

    /**
     * Fetch a remote actor's info.
     *
     * @return array<string, mixed>|null
     */
    public function fetchRemoteActor(string $actorUri): ?array
    {
        // Validate actor URI to prevent SSRF
        try {
            $this->urlValidator->validate($actorUri);
        } catch (InvalidArgumentException $e) {
            Log::warning("ActivityPub: Invalid actor URI rejected: {$actorUri}", [
                'error' => $e->getMessage(),
            ]);

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

    /**
     * Get the client/frontend URL.
     */
    public function getClientUrl(): string
    {
        return rtrim((string) config('app.client_url', config('activitypub.public_domain', $this->getDomain())), '/');
    }

    /**
     * Build a Create activity for a post.
     *
     * @return array<string, mixed>
     */
    public function buildCreateActivity(Post $post): array
    {
        $actorId = $this->getActorId();
        $domain = $this->getDomain();
        $clientUrl = $this->getClientUrl();
        $postUrl = "{$clientUrl}/posts/{$post->slug}";
        $activityId = "{$domain}/activitypub/activities/{$post->id}";
        // Note ID must be on same domain as actor for Mastodon trust
        $noteId = "{$domain}/activitypub/posts/{$post->id}";

        $content = $this->formatPostContent($post);

        $note = [
            'id' => $noteId,
            'type' => 'Note',
            'published' => $post->published_at?->toIso8601String() ?? $post->created_at->toIso8601String(),
            'attributedTo' => $actorId,
            'content' => $content,
            'url' => $postUrl,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => ["{$actorId}/followers"],
        ];

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

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Create',
            'actor' => $actorId,
            'published' => $note['published'],
            'to' => $note['to'],
            'cc' => $note['cc'],
            'object' => $note,
        ];
    }

    /**
     * Format post content for ActivityPub (HTML).
     */
    private function formatPostContent(Post $post): string
    {
        $title = htmlspecialchars($post->title, ENT_QUOTES, 'UTF-8');
        $clientUrl = $this->getClientUrl();
        $postUrl = "{$clientUrl}/posts/{$post->slug}";

        $content = "<p><strong>{$title}</strong></p>";

        if ($post->content !== null && $post->content !== '') {
            $postContent = htmlspecialchars(Str::limit($post->content, 500), ENT_QUOTES, 'UTF-8');
            $content .= "<p>{$postContent}</p>";
        }

        // Add link to our platform
        $content .= "<p>🔗 <a href=\"{$postUrl}\">{$postUrl}</a></p>";

        return $content;
    }

    /**
     * Get follower count.
     */
    public function getFollowerCount(): int
    {
        return ActivityPubFollower::count();
    }

    /**
     * Build a Delete activity for a post.
     *
     * @return array<string, mixed>
     */
    public function buildDeleteActivity(int $postId, string $postSlug): array
    {
        $actorId = $this->getActorId();
        $domain = $this->getDomain();
        $noteId = "{$domain}/activitypub/posts/{$postId}";
        $activityId = "{$domain}/activitypub/activities/{$postId}#delete-" . time();

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Delete',
            'actor' => $actorId,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'object' => [
                'id' => $noteId,
                'type' => 'Tombstone',
            ],
        ];
    }

    /**
     * Build a Delete activity for the actor (account deletion).
     *
     * @return array<string, mixed>
     */
    public function buildDeleteActorActivity(): array
    {
        $actorId = $this->getActorId();
        $activityId = $actorId . '#delete-' . time();

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Delete',
            'actor' => $actorId,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'object' => $actorId,
        ];
    }

    /**
     * Build a Delete activity using the old Note ID format (client URL with slug).
     * Used to delete posts that were sent before the ID format change.
     *
     * @return array<string, mixed>
     */
    public function buildLegacyDeleteActivity(int $postId, string $postSlug): array
    {
        $actorId = $this->getActorId();
        $domain = $this->getDomain();
        $clientUrl = $this->getClientUrl();
        // Old format used client URL with slug as Note ID
        $noteId = "{$clientUrl}/posts/{$postSlug}";
        $activityId = "{$domain}/activitypub/activities/{$postId}#delete-legacy-" . time();

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Delete',
            'actor' => $actorId,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'object' => [
                'id' => $noteId,
                'type' => 'Tombstone',
            ],
        ];
    }

    /**
     * Build a Note object for a post (used when Mastodon fetches the Note).
     *
     * @return array<string, mixed>
     */
    public function buildNoteObject(Post $post): array
    {
        $actorId = $this->getActorId();
        $domain = $this->getDomain();
        $clientUrl = $this->getClientUrl();
        $postUrl = "{$clientUrl}/posts/{$post->slug}";
        $noteId = "{$domain}/activitypub/posts/{$post->id}";

        $content = $this->formatPostContent($post);

        $note = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $noteId,
            'type' => 'Note',
            'published' => $post->published_at?->toIso8601String() ?? $post->created_at->toIso8601String(),
            'attributedTo' => $actorId,
            'content' => $content,
            'url' => $postUrl,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => ["{$actorId}/followers"],
        ];

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
}
