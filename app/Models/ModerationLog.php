<?php

declare(strict_types=1);

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int|null $moderator_id
 * @property int|null $target_user_id
 * @property string $action
 * @property string|null $target_type
 * @property int|null $target_id
 * @property string|null $reason
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read User|null $moderator
 * @property-read Model|Eloquent|null $target
 * @property-read User|null $targetUser
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModerationLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModerationLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModerationLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModerationLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModerationLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModerationLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModerationLog whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModerationLog whereModeratorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModerationLog whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModerationLog whereTargetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModerationLog whereTargetType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModerationLog whereTargetUserId($value)
 *
 * @mixin \Eloquent
 */
final class ModerationLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'moderator_id',
        'target_user_id',
        'action',
        'target_type',
        'target_id',
        'reason',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the moderator who performed the action.
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    /**
     * Get the target user.
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Get the target entity (Post, Comment, etc).
     */
    public function target(): MorphTo
    {
        return $this->morphTo('target', 'target_type', 'target_id');
    }

    /**
     * Create a new moderation log entry.
     */
    public static function logAction(
        int $moderatorId,
        string $action,
        ?int $targetUserId = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $reason = null,
        ?array $metadata = null,
    ): self {
        return self::create([
            'moderator_id' => $moderatorId,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reason' => $reason,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
