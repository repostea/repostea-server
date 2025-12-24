<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $issued_by
 * @property string $type
 * @property string $reason
 * @property string|null $internal_notes
 * @property int|null $related_post_id
 * @property int|null $related_comment_id
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $issuedBy
 * @property-read Comment|null $relatedComment
 * @property-read Post|null $relatedPost
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStrike newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStrike newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStrike query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStrike whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStrike whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStrike whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStrike whereInternalNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStrike whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStrike whereIssuedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStrike whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStrike whereRelatedCommentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStrike whereRelatedPostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStrike whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStrike whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStrike whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class UserStrike extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'issued_by',
        'type',
        'reason',
        'internal_notes',
        'related_post_id',
        'related_comment_id',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user who received the strike.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the moderator who issued the strike.
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Get the related post if any.
     */
    public function relatedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'related_post_id');
    }

    /**
     * Get the related comment if any.
     */
    public function relatedComment(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'related_comment_id');
    }

    /**
     * Check if the strike is currently active.
     */
    public function isActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Check if expired
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if the strike is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
