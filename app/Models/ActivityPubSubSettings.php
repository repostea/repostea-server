<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ActivityPub Sub Settings.
 *
 * Federation settings for a sub/group.
 *
 * @property int $id
 * @property int $sub_id
 * @property bool $federation_enabled
 * @property \Illuminate\Support\Carbon|null $federation_enabled_at
 * @property bool $auto_announce
 * @property bool $accept_remote_posts
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Sub $sub
 */
final class ActivityPubSubSettings extends Model
{
    protected $table = 'activitypub_sub_settings';

    protected $fillable = [
        'sub_id',
        'federation_enabled',
        'federation_enabled_at',
        'auto_announce',
        'accept_remote_posts',
    ];

    protected $casts = [
        'sub_id' => 'integer',
        'federation_enabled' => 'boolean',
        'federation_enabled_at' => 'datetime',
        'auto_announce' => 'boolean',
        'accept_remote_posts' => 'boolean',
    ];

    /**
     * Get the sub.
     */
    public function sub(): BelongsTo
    {
        return $this->belongsTo(Sub::class);
    }

    /**
     * Enable federation for this sub.
     */
    public function enableFederation(): void
    {
        $this->federation_enabled = true;
        $this->federation_enabled_at = now();
        $this->save();

        // Create the group actor if it doesn't exist
        ActivityPubActor::findOrCreateForSub($this->sub);
    }

    /**
     * Disable federation for this sub.
     */
    public function disableFederation(): void
    {
        $this->federation_enabled = false;
        $this->save();

        // Optionally deactivate the actor
        $actor = ActivityPubActor::findByUsername(
            $this->sub->name,
            ActivityPubActor::TYPE_GROUP,
        );

        if ($actor !== null) {
            $actor->update(['is_active' => false]);
        }
    }

    /**
     * Get or create settings for a sub.
     */
    public static function getOrCreate(Sub $sub): self
    {
        return self::firstOrCreate(
            ['sub_id' => $sub->id],
            [
                'federation_enabled' => false,
                'auto_announce' => true,
                'accept_remote_posts' => false,
            ],
        );
    }

    /**
     * Check if the sub should auto-announce federable posts.
     */
    public function shouldAutoAnnounce(): bool
    {
        return $this->federation_enabled && $this->auto_announce;
    }
}
