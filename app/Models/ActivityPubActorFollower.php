<?php

declare(strict_types=1);

namespace App\Models;

use const PHP_URL_HOST;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ActivityPub Actor Follower.
 *
 * Represents a remote actor following one of our actors.
 *
 * @property int $id
 * @property int $actor_id
 * @property string $follower_uri
 * @property string $follower_inbox
 * @property string|null $follower_shared_inbox
 * @property string|null $follower_username
 * @property string $follower_domain
 * @property string|null $follower_name
 * @property string|null $follower_icon
 * @property \Illuminate\Support\Carbon $followed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ActivityPubActor $actor
 * @property-read string|null $instance Aggregate property from stats queries
 * @property-read string|null $first_follow Aggregate property from stats queries
 * @property-read string|null $last_follow Aggregate property from stats queries
 */
final class ActivityPubActorFollower extends Model
{
    protected $table = 'activitypub_actor_followers';

    protected $fillable = [
        'actor_id',
        'follower_uri',
        'follower_inbox',
        'follower_shared_inbox',
        'follower_username',
        'follower_domain',
        'follower_name',
        'follower_icon',
        'followed_at',
    ];

    protected $casts = [
        'actor_id' => 'integer',
        'followed_at' => 'datetime',
    ];

    /**
     * Get the actor being followed.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(ActivityPubActor::class, 'actor_id');
    }

    /**
     * Get the best inbox URL for delivery.
     * Prefers shared inbox for efficiency.
     */
    public function getDeliveryInbox(): string
    {
        return $this->follower_shared_inbox ?? $this->follower_inbox;
    }

    /**
     * Get the follower's handle.
     */
    public function getHandle(): string
    {
        $username = $this->follower_username ?? 'unknown';

        return "@{$username}@{$this->follower_domain}";
    }

    /**
     * Create or update a follower from remote actor data.
     *
     * @param  array<string, mixed>  $remoteActor
     */
    public static function createFromRemoteActor(
        ActivityPubActor $actor,
        string $followerUri,
        array $remoteActor,
    ): self {
        $domain = parse_url($followerUri, PHP_URL_HOST) ?? 'unknown';

        return self::updateOrCreate(
            [
                'actor_id' => $actor->id,
                'follower_uri' => $followerUri,
            ],
            [
                'follower_inbox' => $remoteActor['inbox'] ?? "{$followerUri}/inbox",
                'follower_shared_inbox' => $remoteActor['endpoints']['sharedInbox']
                    ?? $remoteActor['sharedInbox']
                    ?? null,
                'follower_username' => $remoteActor['preferredUsername'] ?? null,
                'follower_domain' => $domain,
                'follower_name' => $remoteActor['name'] ?? null,
                'follower_icon' => $remoteActor['icon']['url'] ?? null,
                'followed_at' => now(),
            ],
        );
    }

    /**
     * Get unique inboxes for a list of followers (for batch delivery).
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, self>  $followers
     *
     * @return array<string>
     */
    public static function getUniqueInboxes($followers): array
    {
        $inboxes = [];

        foreach ($followers as $follower) {
            $inbox = $follower->getDeliveryInbox();
            $inboxes[$inbox] = true;
        }

        return array_keys($inboxes);
    }
}
