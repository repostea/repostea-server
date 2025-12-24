<?php

declare(strict_types=1);

namespace App\Models;

use const PHP_URL_HOST;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * ActivityPub Actor model.
 *
 * Represents any ActivityPub actor in the system:
 * - Instance actor (Application): @repostea@domain
 * - User actor (Person): @username@domain
 * - Group actor (Group): !groupname@domain
 *
 * @property int $id
 * @property string $actor_type 'instance', 'user', 'group'
 * @property string $activitypub_type 'Application', 'Person', 'Group'
 * @property int|null $entity_id
 * @property string $username
 * @property string $preferred_username
 * @property string|null $name
 * @property string|null $summary
 * @property string|null $icon_url
 * @property string $actor_uri
 * @property string $inbox_uri
 * @property string $outbox_uri
 * @property string $followers_uri
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ActivityPubActorKey|null $keys
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ActivityPubActorFollower> $followers
 * @property-read User|null $user
 * @property-read Sub|null $sub
 */
final class ActivityPubActor extends Model
{
    protected $table = 'activitypub_actors';

    protected $fillable = [
        'actor_type',
        'activitypub_type',
        'entity_id',
        'username',
        'preferred_username',
        'name',
        'summary',
        'icon_url',
        'actor_uri',
        'inbox_uri',
        'outbox_uri',
        'followers_uri',
        'is_active',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Actor types.
     */
    public const TYPE_INSTANCE = 'instance';

    public const TYPE_USER = 'user';

    public const TYPE_GROUP = 'group';

    /**
     * ActivityPub types.
     */
    public const AP_APPLICATION = 'Application';

    public const AP_PERSON = 'Person';

    public const AP_GROUP = 'Group';

    /**
     * Get the RSA keys for this actor.
     */
    public function keys(): HasOne
    {
        return $this->hasOne(ActivityPubActorKey::class, 'actor_id');
    }

    /**
     * Get all followers of this actor.
     */
    public function followers(): HasMany
    {
        return $this->hasMany(ActivityPubActorFollower::class, 'actor_id');
    }

    /**
     * Get the associated user (if this is a user actor).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entity_id');
    }

    /**
     * Get the associated sub (if this is a group actor).
     */
    public function sub(): BelongsTo
    {
        return $this->belongsTo(Sub::class, 'entity_id');
    }

    /**
     * Check if this is an instance actor.
     */
    public function isInstance(): bool
    {
        return $this->actor_type === self::TYPE_INSTANCE;
    }

    /**
     * Check if this is a user actor.
     */
    public function isUser(): bool
    {
        return $this->actor_type === self::TYPE_USER;
    }

    /**
     * Check if this is a group actor.
     */
    public function isGroup(): bool
    {
        return $this->actor_type === self::TYPE_GROUP;
    }

    /**
     * Get the handle for this actor.
     * Users: @username@domain
     * Groups: !groupname@domain.
     */
    public function getHandle(): string
    {
        $domain = parse_url($this->actor_uri, PHP_URL_HOST);
        $prefix = $this->isGroup() ? '!' : '@';

        return "{$prefix}{$this->username}@{$domain}";
    }

    /**
     * Get the WebFinger resource identifier.
     */
    public function getWebfingerResource(): string
    {
        $domain = parse_url($this->actor_uri, PHP_URL_HOST);
        $prefix = $this->isGroup() ? '!' : '';

        return "acct:{$prefix}{$this->username}@{$domain}";
    }

    /**
     * Get follower count.
     */
    public function getFollowerCount(): int
    {
        return $this->followers()->count();
    }

    /**
     * Build the ActivityPub actor document.
     *
     * @return array<string, mixed>
     */
    public function toActivityPub(): array
    {
        $document = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
                [
                    'discoverable' => 'toot:discoverable',
                    'toot' => 'http://joinmastodon.org/ns#',
                ],
            ],
            'id' => $this->actor_uri,
            'type' => $this->activitypub_type,
            'preferredUsername' => $this->preferred_username,
            'name' => $this->name ?? $this->preferred_username,
            'inbox' => $this->inbox_uri,
            'outbox' => $this->outbox_uri,
            'followers' => $this->followers_uri,
        ];

        if ($this->summary !== null) {
            $document['summary'] = $this->summary;
        }

        if ($this->icon_url !== null) {
            $document['icon'] = [
                'type' => 'Image',
                'mediaType' => 'image/png',
                'url' => $this->icon_url,
            ];
        }

        // Add public key
        if ($this->keys !== null) {
            $document['publicKey'] = [
                'id' => $this->keys->key_id,
                'owner' => $this->actor_uri,
                'publicKeyPem' => $this->keys->public_key,
            ];
        }

        // Add endpoints for shared inbox (optional optimization)
        $domain = rtrim((string) config('activitypub.domain'), '/');
        $document['endpoints'] = [
            'sharedInbox' => "{$domain}/activitypub/inbox",
        ];

        // Add discoverable flag for user actors (Mastodon extension)
        $document['discoverable'] = $this->isDiscoverable();

        return $document;
    }

    /**
     * Check if this actor should be discoverable in directories.
     */
    public function isDiscoverable(): bool
    {
        // Instance actors are always discoverable
        if ($this->actor_type === self::TYPE_INSTANCE) {
            return true;
        }

        // User actors: check user settings
        if ($this->actor_type === self::TYPE_USER && $this->entity_id !== null) {
            $settings = ActivityPubUserSettings::where('user_id', $this->entity_id)->first();

            return $settings?->indexable ?? true;
        }

        // Group actors: always discoverable for now
        return true;
    }

    /**
     * Check if this actor should show followers count publicly.
     */
    public function shouldShowFollowersCount(): bool
    {
        // Instance and group actors always show followers count
        if ($this->actor_type !== self::TYPE_USER) {
            return true;
        }

        // User actors: check user settings
        if ($this->entity_id !== null) {
            $settings = ActivityPubUserSettings::where('user_id', $this->entity_id)->first();

            return $settings?->show_followers_count ?? true;
        }

        return true;
    }

    /**
     * Get the follower count, respecting user privacy settings.
     * Returns 0 if the user has disabled public follower count.
     */
    public function getPublicFollowerCount(): int
    {
        if (! $this->shouldShowFollowersCount()) {
            return 0;
        }

        return $this->getFollowerCount();
    }

    /**
     * Find or create the instance actor.
     */
    public static function findOrCreateInstanceActor(): self
    {
        $domain = rtrim((string) config('activitypub.domain'), '/');
        $username = config('activitypub.actor.username', 'repostea');

        return self::firstOrCreate(
            ['actor_type' => self::TYPE_INSTANCE],
            [
                'activitypub_type' => self::AP_APPLICATION,
                'entity_id' => null,
                'username' => $username,
                'preferred_username' => $username,
                'name' => config('activitypub.actor.name', 'Repostea'),
                'summary' => config('activitypub.actor.summary'),
                'icon_url' => config('activitypub.actor.icon'),
                'actor_uri' => "{$domain}/activitypub/actor",
                'inbox_uri' => "{$domain}/activitypub/inbox",
                'outbox_uri' => "{$domain}/activitypub/outbox",
                'followers_uri' => "{$domain}/activitypub/followers",
                'is_active' => true,
            ],
        );
    }

    /**
     * Find or create a user actor.
     */
    public static function findOrCreateForUser(User $user): self
    {
        $domain = rtrim((string) config('activitypub.domain'), '/');

        return self::firstOrCreate(
            [
                'actor_type' => self::TYPE_USER,
                'entity_id' => $user->id,
            ],
            [
                'activitypub_type' => self::AP_PERSON,
                'username' => $user->username,
                'preferred_username' => $user->username,
                'name' => $user->display_name,
                'summary' => $user->bio,
                'icon_url' => $user->avatar,
                'actor_uri' => "{$domain}/activitypub/users/{$user->username}",
                'inbox_uri' => "{$domain}/activitypub/users/{$user->username}/inbox",
                'outbox_uri' => "{$domain}/activitypub/users/{$user->username}/outbox",
                'followers_uri' => "{$domain}/activitypub/users/{$user->username}/followers",
                'is_active' => true,
            ],
        );
    }

    /**
     * Find or create a group actor for a sub.
     */
    public static function findOrCreateForSub(Sub $sub): self
    {
        $domain = rtrim((string) config('activitypub.domain'), '/');

        return self::firstOrCreate(
            [
                'actor_type' => self::TYPE_GROUP,
                'entity_id' => $sub->id,
            ],
            [
                'activitypub_type' => self::AP_GROUP,
                'username' => $sub->name,
                'preferred_username' => $sub->name,
                'name' => $sub->display_name,
                'summary' => $sub->description,
                'icon_url' => $sub->icon,
                'actor_uri' => "{$domain}/activitypub/groups/{$sub->name}",
                'inbox_uri' => "{$domain}/activitypub/groups/{$sub->name}/inbox",
                'outbox_uri' => "{$domain}/activitypub/groups/{$sub->name}/outbox",
                'followers_uri' => "{$domain}/activitypub/groups/{$sub->name}/followers",
                'is_active' => true,
            ],
        );
    }

    /**
     * Find actor by username and type.
     */
    public static function findByUsername(string $username, string $actorType): ?self
    {
        return self::where('username', $username)
            ->where('actor_type', $actorType)
            ->where('is_active', true)
            ->first();
    }
}
