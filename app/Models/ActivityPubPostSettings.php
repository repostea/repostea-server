<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ActivityPub Post Settings.
 *
 * Federation settings for a specific post.
 *
 * @property int $id
 * @property int $post_id
 * @property bool $should_federate
 * @property bool $is_federated
 * @property \Illuminate\Support\Carbon|null $federated_at
 * @property string|null $note_uri
 * @property string|null $activity_uri
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Post $post
 */
final class ActivityPubPostSettings extends Model
{
    protected $table = 'activitypub_post_settings';

    protected $fillable = [
        'post_id',
        'should_federate',
        'is_federated',
        'federated_at',
        'note_uri',
        'activity_uri',
    ];

    protected $casts = [
        'post_id' => 'integer',
        'should_federate' => 'boolean',
        'is_federated' => 'boolean',
        'federated_at' => 'datetime',
    ];

    /**
     * Get the post.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Mark the post as federated.
     */
    public function markAsFederated(string $noteUri, string $activityUri): void
    {
        $this->is_federated = true;
        $this->federated_at = now();
        $this->note_uri = $noteUri;
        $this->activity_uri = $activityUri;
        $this->save();
    }

    /**
     * Get or create settings for a post.
     *
     * @param  bool|null  $shouldFederate  If null, uses user's default preference
     */
    public static function getOrCreate(Post $post, ?bool $shouldFederate = null): self
    {
        $existing = self::where('post_id', $post->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        // Determine default from user settings if not specified
        if ($shouldFederate === null) {
            $userSettings = ActivityPubUserSettings::getOrCreate($post->user);
            $shouldFederate = $userSettings->shouldFederateByDefault();
        }

        return self::create([
            'post_id' => $post->id,
            'should_federate' => $shouldFederate,
            'is_federated' => false,
        ]);
    }

    /**
     * Check if a post can be federated.
     *
     * Requirements:
     * - Post settings: should_federate = true
     * - User settings: federation_enabled = true
     * - Sub settings (if in sub): federation_enabled = true
     */
    public static function canFederate(Post $post): bool
    {
        // Check post is published (not draft/hidden)
        if ($post->status !== Post::STATUS_PUBLISHED) {
            return false;
        }

        // Get post settings
        $postSettings = self::where('post_id', $post->id)->first();
        if ($postSettings === null || ! $postSettings->should_federate) {
            return false;
        }

        // Check user federation is enabled
        $userSettings = ActivityPubUserSettings::where('user_id', $post->user_id)->first();
        if ($userSettings === null || ! $userSettings->federation_enabled) {
            return false;
        }

        // Check sub federation if post is in a sub
        if ($post->sub_id !== null) {
            $subSettings = ActivityPubSubSettings::where('sub_id', $post->sub_id)->first();
            // If sub has settings and federation is disabled, don't federate
            // If sub has no settings, allow (sub federation is optional)
            if ($subSettings !== null && ! $subSettings->federation_enabled) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get posts that are ready to federate.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function getPendingFederation(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('should_federate', true)
            ->where('is_federated', false)
            ->with('post.user', 'post.sub')
            ->get()
            ->filter(fn (self $settings) => self::canFederate($settings->post));
    }
}
