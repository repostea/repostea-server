<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $relationship_id
 * @property int $user_id
 * @property int $vote 1 for upvote, -1 for downvote
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read PostRelationship $relationship
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RelationshipVote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RelationshipVote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RelationshipVote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RelationshipVote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RelationshipVote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RelationshipVote whereRelationshipId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RelationshipVote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RelationshipVote whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RelationshipVote whereVote($value)
 *
 * @mixin \Eloquent
 */
final class RelationshipVote extends Model
{
    protected $fillable = [
        'relationship_id',
        'user_id',
        'vote',
    ];

    protected $casts = [
        'vote' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the relationship this vote belongs to.
     */
    public function relationship(): BelongsTo
    {
        return $this->belongsTo(PostRelationship::class, 'relationship_id');
    }

    /**
     * Get the user who cast this vote.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this is an upvote.
     */
    public function isUpvote(): bool
    {
        return $this->vote === 1;
    }

    /**
     * Check if this is a downvote.
     */
    public function isDownvote(): bool
    {
        return $this->vote === -1;
    }
}
