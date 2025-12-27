<?php

declare(strict_types=1);

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string $content
 * @property int|null $user_id
 * @property int|null $remote_user_id
 * @property int $post_id
 * @property int|null $parent_id
 * @property int $votes_count
 * @property bool $is_anonymous
 * @property string $status
 * @property string $source
 * @property string|null $source_uri
 * @property int|null $moderated_by
 * @property string|null $moderation_reason
 * @property \Illuminate\Support\Carbon|null $moderated_at
 * @property \Illuminate\Support\Carbon|null $edited_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read RemoteUser|null $remoteUser
 * @property-read Post $post
 * @property-read Comment|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Comment> $replies
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Vote> $votes
 * @property bool|int $user_has_recommended Dynamic property from seal mark queries
 * @property bool|int $user_has_advise_against Dynamic property from seal mark queries
 * @property int $recommended_seals_count
 * @property int $advise_against_seals_count
 * @property-read User|null $moderatedBy
 * @property-read int|null $replies_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SealMark> $sealMarks
 * @property-read int|null $seal_marks_count
 *
 * @method static \Database\Factories\CommentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereAdviseAgainstSealsCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereEditedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereIsAnonymous($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereModeratedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereModeratedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereModerationReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment wherePostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereRecommendedSealsCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereVotesCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment withUserSealMarks(?int $userId = null)
 *
 * @mixin \Eloquent
 */
final class Comment extends Model
{
    use HasFactory;

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_HIDDEN = 'hidden';

    public const STATUS_DELETED_BY_MODERATOR = 'deleted_by_moderator';

    public const STATUS_DELETED_BY_AUTHOR = 'deleted_by_author';

    protected $fillable = [
        'content',
        'user_id',
        'remote_user_id',
        'post_id',
        'parent_id',
        'votes_count',
        'is_anonymous',
        'status',
        'source',
        'source_uri',
        'moderated_by',
        'moderation_reason',
        'moderated_at',
        'edited_at',
    ];

    protected $casts = [
        'votes_count' => 'integer',
        'is_anonymous' => 'boolean',
        'moderated_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    /**
     * Get the user that owns the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Get the remote user that owns the comment (for federated comments).
     */
    public function remoteUser(): BelongsTo
    {
        return $this->belongsTo(RemoteUser::class);
    }

    /**
     * Check if this comment is from a remote/federated user.
     */
    public function isRemote(): bool
    {
        return $this->remote_user_id !== null;
    }

    /**
     * Get the author display name (local or remote).
     */
    public function getAuthorDisplayName(): string
    {
        if ($this->isRemote() && $this->remoteUser) {
            return $this->remoteUser->display_name_or_username;
        }

        return $this->getDisplayUsername();
    }

    /**
     * Get the author handle (username or @user@domain for remote).
     */
    public function getAuthorHandle(): string
    {
        if ($this->isRemote() && $this->remoteUser) {
            return $this->remoteUser->handle;
        }

        if ($this->user) {
            return $this->user->username;
        }

        return '[deleted]';
    }

    /**
     * Get the display username for this comment.
     * Returns '[deleted]' if user is null or soft-deleted.
     */
    public function getDisplayUsername(): string
    {
        if (! $this->user || $this->user->trashed()) {
            return '[deleted]';
        }

        return $this->user->username;
    }

    /**
     * Check if the comment's author has been deleted.
     */
    public function hasDeletedAuthor(): bool
    {
        return ! $this->user || $this->user->trashed();
    }

    /**
     * Get the moderator who moderated this comment.
     */
    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /**
     * Get the post that owns the comment.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get the parent comment.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get replies to this comment.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get votes for this comment.
     */
    public function votes(): MorphMany
    {
        return $this->morphMany(Vote::class, 'votable');
    }

    /**
     * Get seal marks for this comment.
     */
    public function sealMarks(): MorphMany
    {
        return $this->morphMany(SealMark::class, 'markable');
    }

    /**
     * Update the vote count based on actual votes.
     */
    public function updateVotesCount()
    {
        $upvotes = $this->votes()->where('value', 1)->count();
        $downvotes = $this->votes()->where('value', -1)->count();

        $this->votes_count = $upvotes - $downvotes;
        $this->save();

        return $this->votes_count;
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
            $join->on('comments.id', '=', 'user_seal_recommended.markable_id')
                ->where('user_seal_recommended.markable_type', '=', self::class)
                ->where('user_seal_recommended.user_id', '=', $userId)
                ->where('user_seal_recommended.type', '=', 'recommended')
                ->where('user_seal_recommended.expires_at', '>', now());
        })
            ->leftJoin('seal_marks as user_seal_advise', function ($join) use ($userId): void {
                $join->on('comments.id', '=', 'user_seal_advise.markable_id')
                    ->where('user_seal_advise.markable_type', '=', self::class)
                    ->where('user_seal_advise.user_id', '=', $userId)
                    ->where('user_seal_advise.type', '=', 'advise_against')
                    ->where('user_seal_advise.expires_at', '>', now());
            })
            ->addSelect(
                'comments.*',
                DB::raw('CASE WHEN user_seal_recommended.id IS NOT NULL THEN 1 ELSE 0 END as user_has_recommended'),
                DB::raw('CASE WHEN user_seal_advise.id IS NOT NULL THEN 1 ELSE 0 END as user_has_advise_against'),
            );
    }
}
