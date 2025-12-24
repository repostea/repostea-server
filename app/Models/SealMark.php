<?php

declare(strict_types=1);

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $markable_type
 * @property int $markable_id
 * @property string|null $type
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|Eloquent $markable
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SealMark active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SealMark adviseAgainst()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SealMark expired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SealMark newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SealMark newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SealMark query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SealMark recommended()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SealMark whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SealMark whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SealMark whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SealMark whereMarkableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SealMark whereMarkableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SealMark whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SealMark whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SealMark whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class SealMark extends Model
{
    public const TYPE_RECOMMENDED = 'recommended';

    public const TYPE_ADVISE_AGAINST = 'advise_against';

    protected $fillable = [
        'user_id',
        'markable_id',
        'markable_type',
        'type',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user who applied the seal.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the marked content (Post or Comment).
     */
    public function markable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if seal mark is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Scope to get only active (non-expired) marks.
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to get only expired marks.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope to get recommended marks.
     */
    public function scopeRecommended($query)
    {
        return $query->where('type', self::TYPE_RECOMMENDED);
    }

    /**
     * Scope to get advise against marks.
     */
    public function scopeAdviseAgainst($query)
    {
        return $query->where('type', self::TYPE_ADVISE_AGAINST);
    }
}
