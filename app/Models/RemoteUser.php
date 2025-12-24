<?php

declare(strict_types=1);

namespace App\Models;

use const PHP_URL_HOST;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a user from a remote ActivityPub instance.
 *
 * @property int $id
 * @property string $actor_uri
 * @property string $username
 * @property string $domain
 * @property string|null $display_name
 * @property string|null $avatar_url
 * @property string|null $profile_url
 * @property string|null $software
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Comment> $comments
 * @property-read int|null $comments_count
 * @property-read string|null $instance Aggregate property from stats queries
 */
final class RemoteUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_uri',
        'username',
        'domain',
        'display_name',
        'avatar_url',
        'profile_url',
        'software',
        'metadata',
        'last_fetched_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_fetched_at' => 'datetime',
    ];

    /**
     * Get the full handle (username@domain).
     */
    public function getHandleAttribute(): string
    {
        return "@{$this->username}@{$this->domain}";
    }

    /**
     * Get the display name or fall back to username.
     */
    public function getDisplayNameOrUsernameAttribute(): string
    {
        return $this->display_name ?? $this->username;
    }

    /**
     * Get comments made by this remote user.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get the software icon class based on the software type.
     */
    public function getSoftwareIconAttribute(): string
    {
        return match ($this->software) {
            'mastodon' => 'mastodon',
            'lemmy' => 'lemmy',
            'pleroma' => 'pleroma',
            'misskey' => 'misskey',
            'kbin', 'mbin' => 'kbin',
            'pixelfed' => 'pixelfed',
            'peertube' => 'peertube',
            'friendica' => 'friendica',
            default => 'fediverse',
        };
    }

    /**
     * Find or create a remote user from ActivityPub actor data.
     */
    public static function findOrCreateFromActor(array $actorData): self
    {
        $actorUri = $actorData['id'] ?? $actorData['actor_uri'];

        return self::updateOrCreate(
            ['actor_uri' => $actorUri],
            [
                'username' => $actorData['preferredUsername'] ?? $actorData['username'] ?? 'unknown',
                'domain' => parse_url($actorUri, PHP_URL_HOST) ?? 'unknown',
                'display_name' => $actorData['name'] ?? $actorData['display_name'] ?? null,
                'avatar_url' => $actorData['icon']['url'] ?? $actorData['avatar_url'] ?? null,
                'profile_url' => $actorData['url'] ?? $actorUri,
                'software' => $actorData['software'] ?? null,
                'metadata' => $actorData['metadata'] ?? null,
                'last_fetched_at' => now(),
            ],
        );
    }
}
