<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $source_post_id
 * @property int $target_post_id
 * @property string $relationship_type
 * @property string $relation_category
 * @property int $created_by
 * @property string|null $notes
 * @property bool $is_anonymous
 * @property int $upvotes_count
 * @property int $downvotes_count
 * @property int $score
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Post $sourcePost
 * @property-read Post $targetPost
 * @property-read User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RelationshipVote> $votes
 * @property-read int|null $votes_count
 *
 * @method static \Database\Factories\PostRelationshipFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship forPost(int $postId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship ofType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship whereDownvotesCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship whereIsAnonymous($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship whereRelationCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship whereRelationshipType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship whereSourcePostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship whereTargetPostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostRelationship whereUpvotesCount($value)
 *
 * @mixin \Eloquent
 */
final class PostRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_post_id',
        'target_post_id',
        'relationship_type',
        'relation_category',
        'created_by',
        'notes',
        'is_anonymous',
        'upvotes_count',
        'downvotes_count',
        'score',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'upvotes_count' => 'integer',
        'downvotes_count' => 'integer',
        'score' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship types constants.
     */
    public const TYPE_REPLY = 'reply';

    public const TYPE_CONTINUATION = 'continuation';

    public const TYPE_RELATED = 'related';

    public const TYPE_UPDATE = 'update';

    public const TYPE_CORRECTION = 'correction';

    public const TYPE_DUPLICATE = 'duplicate';

    /**
     * Relation category constants.
     */
    public const CATEGORY_OWN = 'own';

    public const CATEGORY_EXTERNAL = 'external';

    /**
     * Own content relationship types (author only, relationship type required).
     */
    public const OWN_CONTENT_TYPES = [
        self::TYPE_CONTINUATION,
        self::TYPE_CORRECTION,
    ];

    /**
     * External content relationship types (any user can create).
     */
    public const EXTERNAL_CONTENT_TYPES = [
        self::TYPE_UPDATE,
        self::TYPE_REPLY,
        self::TYPE_RELATED,
        self::TYPE_DUPLICATE,
    ];

    /**
     * Relationship types that only the author can create.
     */
    public const AUTHOR_ONLY_TYPES = [
        self::TYPE_CONTINUATION,
        self::TYPE_CORRECTION,
    ];

    /**
     * Get all valid relationship types.
     */
    public static function getRelationshipTypes(): array
    {
        return [
            self::TYPE_REPLY,
            self::TYPE_CONTINUATION,
            self::TYPE_RELATED,
            self::TYPE_UPDATE,
            self::TYPE_CORRECTION,
            self::TYPE_DUPLICATE,
        ];
    }

    /**
     * Check if a relationship type requires author permission.
     */
    public static function requiresAuthor(string $type): bool
    {
        return in_array($type, self::AUTHOR_ONLY_TYPES);
    }

    /**
     * Get the relation category for a given type.
     */
    public static function getCategoryForType(string $type): string
    {
        if (in_array($type, self::OWN_CONTENT_TYPES)) {
            return self::CATEGORY_OWN;
        }

        return self::CATEGORY_EXTERNAL;
    }

    /**
     * Get relationship types for a specific category.
     */
    public static function getTypesByCategory(string $category): array
    {
        return $category === self::CATEGORY_OWN
            ? self::OWN_CONTENT_TYPES
            : self::EXTERNAL_CONTENT_TYPES;
    }

    /**
     * Check if a relationship type is for own content.
     */
    public static function isOwnContentType(string $type): bool
    {
        return in_array($type, self::OWN_CONTENT_TYPES);
    }

    /**
     * Check if reply is allowed (reply cannot be to own post).
     */
    public static function canReplyToPost(int $currentUserId, int $targetPostAuthorId): bool
    {
        // Reply is only allowed if the target post is NOT by the current user
        return $currentUserId !== $targetPostAuthorId;
    }

    /**
     * Source post (the post that has the relationship).
     */
    public function sourcePost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'source_post_id');
    }

    /**
     * Target post (the post being related to).
     */
    public function targetPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'target_post_id');
    }

    /**
     * User who created this relationship.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to filter by relationship type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('relationship_type', $type);
    }

    /**
     * Scope to get relationships for a specific post (both as source and target).
     */
    public function scopeForPost($query, int $postId)
    {
        return $query->where('source_post_id', $postId)
            ->orWhere('target_post_id', $postId);
    }

    /**
     * Get all votes for this relationship.
     */
    public function votes(): HasMany
    {
        return $this->hasMany(RelationshipVote::class, 'relationship_id');
    }

    /**
     * Update vote counts from the votes table.
     */
    public function updateVoteCounts(): void
    {
        $upvotes = $this->votes()->where('vote', 1)->count();
        $downvotes = $this->votes()->where('vote', -1)->count();

        $this->upvotes_count = $upvotes;
        $this->downvotes_count = $downvotes;
        $this->score = $upvotes - $downvotes;

        $this->save();
    }

    /**
     * Get the total score (upvotes - downvotes).
     */
    public function getScore(): int
    {
        return $this->score ?? 0;
    }
}
