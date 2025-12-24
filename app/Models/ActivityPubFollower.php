<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents a remote ActivityPub actor following our instance.
 *
 * @property int $id
 * @property string $actor_id
 * @property string $inbox_url
 * @property string|null $shared_inbox_url
 * @property string|null $username
 * @property string $domain
 * @property string|null $display_name
 * @property string|null $avatar_url
 * @property \Carbon\Carbon $followed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string $best_inbox
 * @property-read string $handle
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityPubFollower newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityPubFollower newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityPubFollower query()
 *
 * @mixin \Eloquent
 */
final class ActivityPubFollower extends Model
{
    protected $table = 'activitypub_followers';

    protected $fillable = [
        'actor_id',
        'inbox_url',
        'shared_inbox_url',
        'username',
        'domain',
        'display_name',
        'avatar_url',
        'followed_at',
    ];

    protected function casts(): array
    {
        return [
            'followed_at' => 'datetime',
        ];
    }

    /**
     * Get the handle in @user@domain format.
     */
    public function getHandleAttribute(): string
    {
        return $this->username !== null
            ? "@{$this->username}@{$this->domain}"
            : $this->actor_id;
    }

    /**
     * Get the best inbox URL (prefer shared for efficiency).
     */
    public function getBestInboxAttribute(): string
    {
        return $this->shared_inbox_url ?? $this->inbox_url;
    }

    /**
     * Get unique shared inboxes for batch delivery.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function getUniqueInboxes(): \Illuminate\Support\Collection
    {
        return self::query()
            ->select('shared_inbox_url', 'inbox_url')
            ->get()
            ->map(fn (self $follower) => $follower->best_inbox)
            ->unique()
            ->values();
    }
}
