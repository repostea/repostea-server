<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ActivityPub User Settings.
 *
 * Federation preferences for a user.
 *
 * @property int $id
 * @property int $user_id
 * @property bool $federation_enabled
 * @property \Illuminate\Support\Carbon|null $federation_enabled_at
 * @property bool $default_federate_posts
 * @property bool $indexable
 * @property bool $show_followers_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 */
final class ActivityPubUserSettings extends Model
{
    protected $table = 'activitypub_user_settings';

    protected $fillable = [
        'user_id',
        'federation_enabled',
        'federation_enabled_at',
        'default_federate_posts',
        'indexable',
        'show_followers_count',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'federation_enabled' => 'boolean',
        'federation_enabled_at' => 'datetime',
        'default_federate_posts' => 'boolean',
        'indexable' => 'boolean',
        'show_followers_count' => 'boolean',
    ];

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Enable federation for this user.
     */
    public function enableFederation(): void
    {
        $this->federation_enabled = true;
        $this->federation_enabled_at = now();
        $this->save();

        // Create the actor if it doesn't exist
        ActivityPubActor::findOrCreateForUser($this->user);
    }

    /**
     * Disable federation for this user.
     */
    public function disableFederation(): void
    {
        $this->federation_enabled = false;
        $this->save();

        // Optionally deactivate the actor
        $actor = ActivityPubActor::findByUsername(
            $this->user->username,
            ActivityPubActor::TYPE_USER,
        );

        if ($actor !== null) {
            $actor->update(['is_active' => false]);
        }
    }

    /**
     * Get or create settings for a user.
     */
    public static function getOrCreate(User $user): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id],
            [
                'federation_enabled' => false,
                'default_federate_posts' => false,
                'indexable' => true,
                'show_followers_count' => true,
            ],
        );
    }

    /**
     * Check if the user should have their new posts federated by default.
     */
    public function shouldFederateByDefault(): bool
    {
        return $this->federation_enabled && $this->default_federate_posts;
    }
}
