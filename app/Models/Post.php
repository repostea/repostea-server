<?php

declare(strict_types=1);

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property string|null $content
 * @property string|null $url
 * @property string|null $slug
 * @property string|null $uuid
 * @property int $user_id
 * @property int|null $sub_id
 * @property string $status
 * @property string|null $content_type
 * @property string|null $media_provider
 * @property int $votes_count
 * @property int $comment_count
 * @property int $views
 * @property bool $is_original
 * @property bool $is_anonymous
 * @property bool $is_nsfw
 * @property int|null $thumbnail_image_id
 * @property \Illuminate\Support\Carbon|null $frontpage_at
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $user_vote
 * @property string|null $user_vote_type
 * @property array|null $vote_details
 * @property int $votes
 * @property bool|int $is_visited Dynamic property from view queries
 * @property \Illuminate\Support\Carbon|null $last_visited_at Dynamic property from view queries
 * @property int $new_comments_count Dynamic property from view queries
 * @property bool|int $user_has_recommended Dynamic property from seal mark queries
 * @property bool|int $user_has_advise_against Dynamic property from seal mark queries
 * @property string|null $thumbnail_url
 * @property string $type
 * @property bool $nsfw_locked_by_admin
 * @property int $recommended_seals_count
 * @property int $advise_against_seals_count
 * @property int $impressions
 * @property int $total_views
 * @property string|null $source
 * @property string $language_code
 * @property bool $language_locked_by_admin
 * @property int $is_external_import
 * @property-read int|null $total_likes Aggregate property from federation stats queries
 * @property-read int|null $total_shares Aggregate property from federation stats queries
 * @property-read int|null $total_replies Aggregate property from federation stats queries
 * @property-read int|null $posts_with_engagement Aggregate property from federation stats queries
 * @property string|null $external_id
 * @property string|null $source_name
 * @property string|null $source_url
 * @property string|null $external_source
 * @property string|null $original_published_at
 * @property array<array-key, mixed>|null $media_metadata
 * @property string|null $media_url
 * @property \Illuminate\Support\Carbon|null $twitter_posted_at
 * @property string|null $twitter_tweet_id
 * @property string|null $twitter_post_method
 * @property string|null $twitter_post_reason
 * @property int|null $twitter_posted_by
 * @property int|null $moderated_by
 * @property string|null $moderation_reason
 * @property string|null $previous_status
 * @property \Illuminate\Support\Carbon|null $moderated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Comment> $comments
 * @property-read int|null $comments_count
 * @property-read User|null $moderatedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PollVote> $pollVotes
 * @property-read int|null $poll_votes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PostRelationship> $relationshipsAsSource
 * @property-read int|null $relationships_as_source_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PostRelationship> $relationshipsAsTarget
 * @property-read int|null $relationships_as_target_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Report> $reports
 * @property-read int|null $reports_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SealMark> $sealMarks
 * @property-read int|null $seal_marks_count
 * @property-read Sub|null $sub
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 * @property-read Image|null $thumbnailImage
 * @property-read User|null $twitterPostedBy
 * @property-read User|null $user
 * @property-read int|null $views_count
 *
 * @method static \Database\Factories\PostFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereAdviseAgainstSealsCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereCommentCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereContentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereExternalSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereFrontpageAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereImpressions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereIsAnonymous($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereIsExternalImport($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereIsNsfw($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereIsOriginal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereLanguageCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereLanguageLockedByAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereMediaMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereMediaProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereMediaUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereModeratedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereModeratedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereModerationReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereNsfwLockedByAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereOriginalPublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post wherePreviousStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereRecommendedSealsCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereSourceName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereSourceUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereSubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereThumbnailImageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereThumbnailUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereTotalViews($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereTwitterPostMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereTwitterPostReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereTwitterPostedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereTwitterPostedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereTwitterTweetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereViews($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereVotesCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post withUserSealMarks(?int $userId = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class Post extends Model
{
    use HasFactory;

    use SoftDeletes;

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_HIDDEN = 'hidden';

    protected $fillable = [
        'title',
        'content',
        'url',
        'thumbnail_url',
        'user_id',
        'sub_id',
        'is_original',
        'is_anonymous',
        'is_nsfw',
        'status',
        'votes_count',
        'comment_count',
        'views',
        'source',
        'source_name',
        'source_url',
        'external_source',
        'language_code',
        'language_locked_by_admin',
        'nsfw_locked_by_admin',
        'slug',
        'uuid',
        'content_type',
        'media_provider',
        'media_metadata',
        'moderated_by',
        'moderation_reason',
        'previous_status',
        'moderated_at',
        'frontpage_at',
        'published_at',
        'twitter_posted_at',
        'twitter_tweet_id',
        'twitter_post_method',
        'twitter_post_reason',
        'twitter_posted_by',
    ];

    protected $casts = [
        'is_original' => 'boolean',
        'is_anonymous' => 'boolean',
        'is_nsfw' => 'boolean',
        'language_locked_by_admin' => 'boolean',
        'nsfw_locked_by_admin' => 'boolean',
        'votes_count' => 'integer',
        'comment_count' => 'integer',
        'views' => 'integer',
        'media_metadata' => 'array',
        'moderated_at' => 'datetime',
        'frontpage_at' => 'datetime',
        'published_at' => 'datetime',
        'twitter_posted_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        self::creating(static function ($post): void {
            if (empty($post->slug)) {
                $post->generateSlug();
            }
            if (empty($post->uuid)) {
                $post->uuid = (string) Str::uuid();
            }

            if (empty($post->content_type)) {
                $post->content_type = 'link';
            }

            // Set published_at on first publish (only if creating as published)
            if ($post->status === self::STATUS_PUBLISHED && empty($post->published_at)) {
                $post->published_at = now();
            }
        });

        self::updating(static function ($post): void {
            if ($post->isDirty('title') && ! $post->isDirty('slug')) {
                $post->generateSlug();
            }

            // Set published_at on first publish (when status changes to published)
            if ($post->isDirty('status') && $post->status === self::STATUS_PUBLISHED && empty($post->published_at)) {
                $post->published_at = now();
            }
        });
    }

    public function getPermalinkUrl()
    {
        return "/p/{$this->uuid}";
    }

    public function generateSlug(): void
    {
        $this->slug = Str::slug($this->title);

        // If slug is numeric only, add prefix to avoid confusion with IDs
        if (preg_match('/^\d+$/', $this->slug)) {
            $this->slug = 'post-' . $this->slug;
        }

        $count = 1;
        $originalSlug = $this->slug;

        while (self::where('slug', $this->slug)
            ->where('id', '!=', $this->id ?? 0)
            ->whereNull('deleted_at') // Exclude deleted posts
            ->exists()) {
            $this->slug = $originalSlug . '-' . $count++;
        }
    }

    public function getRoute()
    {
        return "/posts/{$this->slug}";
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Get the sub this post belongs to.
     */
    public function sub(): BelongsTo
    {
        return $this->belongsTo(Sub::class);
    }

    /**
     * Get the ActivityPub settings for this post.
     */
    public function activityPubSettings(): HasOne
    {
        return $this->hasOne(ActivityPubPostSettings::class);
    }

    /**
     * Get the display username for this post.
     * Returns '[deleted]' if user is null or soft-deleted.
     * Returns anonymous text if post is anonymous.
     */
    public function getDisplayUsername(): string
    {
        if (! $this->user || $this->user->trashed()) {
            return '[deleted]';
        }

        if ($this->is_anonymous) {
            return __('common.anonymous');
        }

        return $this->user->username;
    }

    /**
     * Check if the post's author has been deleted.
     */
    public function hasDeletedAuthor(): bool
    {
        return ! $this->user || $this->user->trashed();
    }

    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function twitterPostedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'twitter_posted_by');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function syncTags(array $tagIds = []): void
    {
        if (! empty($tagIds)) {
            $this->tags()->sync($tagIds);
        }
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function pollVotes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(PostView::class);
    }

    public function votes(): MorphMany
    {
        return $this->morphMany(Vote::class, 'votable');
    }

    public function sealMarks()
    {
        return $this->morphMany(SealMark::class, 'markable');
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function updateVotesCount(): void
    {
        $upvotes = $this->votes()->where('value', 1)->count();
        $this->votes_count = $upvotes;
        $this->save();
    }

    public function isExternalImport(): bool
    {
        return $this->external_source !== null && $this->external_source !== '';
    }

    public function getSourceUrl(): ?string
    {
        return $this->source_url !== null ? $this->source_url : $this->url;
    }

    public function getSourceName(): ?string
    {
        if ($this->source_name !== null) {
            return $this->source_name;
        }

        if ($this->source !== null) {
            return $this->source;
        }

        return $this->external_source;
    }

    public function isMediaContent(): bool
    {
        return in_array($this->content_type, ['video', 'audio', 'image'], true);
    }

    /**
     * Scope to include user's seal marks (recommended/advise_against).
     */
    public function scopeWithUserSealMarks($query, ?int $userId = null)
    {
        if (! $userId) {
            return $query;
        }

        return $query->leftJoin('seal_marks as user_seal_recommended', function ($join) use ($userId): void {
            $join->on('posts.id', '=', 'user_seal_recommended.markable_id')
                ->where('user_seal_recommended.markable_type', '=', self::class)
                ->where('user_seal_recommended.user_id', '=', $userId)
                ->where('user_seal_recommended.type', '=', 'recommended')
                ->where('user_seal_recommended.expires_at', '>', now());
        })
            ->leftJoin('seal_marks as user_seal_advise', function ($join) use ($userId): void {
                $join->on('posts.id', '=', 'user_seal_advise.markable_id')
                    ->where('user_seal_advise.markable_type', '=', self::class)
                    ->where('user_seal_advise.user_id', '=', $userId)
                    ->where('user_seal_advise.type', '=', 'advise_against')
                    ->where('user_seal_advise.expires_at', '>', now());
            })
            ->addSelect(
                'posts.*',
                DB::raw('CASE WHEN user_seal_recommended.id IS NOT NULL THEN 1 ELSE 0 END as user_has_recommended'),
                DB::raw('CASE WHEN user_seal_advise.id IS NOT NULL THEN 1 ELSE 0 END as user_has_advise_against'),
            );
    }

    public function getFormattedMediaProvider(): ?string
    {
        if ($this->media_provider === null) {
            return null;
        }

        $providers = [
            'youtube' => 'YouTube',
            'vimeo' => 'Vimeo',
            'soundcloud' => 'SoundCloud',
            'spotify' => 'Spotify',
            'apple_podcasts' => 'Apple Podcasts',
        ];

        return $providers[$this->media_provider] ?? $this->media_provider;
    }

    /**
     * Get the thumbnail image.
     */
    public function thumbnailImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'thumbnail_image_id');
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->thumbnail_image_id && $this->relationLoaded('thumbnailImage') && $this->thumbnailImage) {
            return $this->thumbnailImage->getUrl();
        }

        return $this->attributes['thumbnail_url'] ?? null;
    }

    /**
     * Get relationships where this post is the source.
     */
    public function relationshipsAsSource(): HasMany
    {
        return $this->hasMany(PostRelationship::class, 'source_post_id');
    }

    /**
     * Get relationships where this post is the target.
     */
    public function relationshipsAsTarget(): HasMany
    {
        return $this->hasMany(PostRelationship::class, 'target_post_id');
    }

    /**
     * Get all relationships for this post (both as source and target).
     */
    public function allRelationships()
    {
        return PostRelationship::where('source_post_id', $this->id)
            ->orWhere('target_post_id', $this->id)
            ->with(['sourcePost.user', 'targetPost.user', 'creator'])
            ->get();
    }

    /**
     * Get related posts of a specific type.
     */
    public function getRelatedPosts(string $type): \Illuminate\Support\Collection
    {
        $asSource = $this->relationshipsAsSource()
            ->where('relationship_type', $type)
            ->with('targetPost')
            ->get()
            ->pluck('targetPost');

        $asTarget = $this->relationshipsAsTarget()
            ->where('relationship_type', $type)
            ->with('sourcePost')
            ->get()
            ->pluck('sourcePost');

        return $asSource->merge($asTarget);
    }

    /**
     * Get continuation chain (for breadcrumbs).
     */
    public function getContinuationChain(): array
    {
        $chain = [];
        $current = $this;
        $visitedIds = []; // Track visited post IDs to prevent infinite loops
        $maxDepth = 50; // Maximum chain length to prevent infinite loops
        $depth = 0;

        // Go backwards to find the start of the chain
        while ($depth < $maxDepth && $previous = $current->relationshipsAsSource()
            ->where('relationship_type', PostRelationship::TYPE_CONTINUATION)
            ->with('targetPost')
            ->first()) {

            // Check if we've already visited this post (circular reference)
            if (in_array($previous->targetPost->id, $visitedIds)) {
                break;
            }

            $visitedIds[] = $previous->targetPost->id;
            array_unshift($chain, $previous->targetPost);
            $current = $previous->targetPost;
            $depth++;
        }

        // Add current post
        $chain[] = $this;
        $visitedIds[] = $this->id;

        // Go forward to find the rest of the chain
        $current = $this;
        $depth = 0;
        while ($depth < $maxDepth && $next = $current->relationshipsAsTarget()
            ->where('relationship_type', PostRelationship::TYPE_CONTINUATION)
            ->with('sourcePost')
            ->first()) {

            // Check if we've already visited this post (circular reference)
            if (in_array($next->sourcePost->id, $visitedIds)) {
                break;
            }

            $visitedIds[] = $next->sourcePost->id;
            $chain[] = $next->sourcePost;
            $current = $next->sourcePost;
            $depth++;
        }

        return $chain;
    }
}
