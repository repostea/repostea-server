<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $content_type
 * @property int $content_id
 * @property string $detection_type
 * @property float|null $similarity
 * @property int|null $spam_score
 * @property string|null $risk_level
 * @property array<array-key, mixed>|null $reasons
 * @property array<array-key, mixed>|null $metadata
 * @property bool $reviewed
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property string|null $action_taken
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $reviewer
 * @property-read User $user
 *
 * @method static \Database\Factories\SpamDetectionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection whereActionTaken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection whereContentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection whereContentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection whereDetectionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection whereReasons($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection whereReviewed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection whereReviewedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection whereReviewedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection whereRiskLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection whereSimilarity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection whereSpamScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamDetection whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class SpamDetection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'content_type',
        'content_id',
        'detection_type',
        'similarity',
        'spam_score',
        'risk_level',
        'reasons',
        'metadata',
        'reviewed',
        'reviewed_by',
        'reviewed_at',
        'action_taken',
    ];

    protected $casts = [
        'similarity' => 'float',
        'spam_score' => 'integer',
        'reasons' => 'array',
        'metadata' => 'array',
        'reviewed' => 'boolean',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function post(): ?BelongsTo
    {
        return $this->content_type === 'post'
            ? $this->belongsTo(Post::class, 'content_id')
            : null;
    }

    public function comment(): ?BelongsTo
    {
        return $this->content_type === 'comment'
            ? $this->belongsTo(Comment::class, 'content_id')
            : null;
    }
}
