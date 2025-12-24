<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $banned_by
 * @property string $type
 * @property string $reason
 * @property string|null $internal_notes
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $bannedBy
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBan whereBannedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBan whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBan whereInternalNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBan whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBan whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBan whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBan whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBan whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class UserBan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'banned_by',
        'type',
        'reason',
        'internal_notes',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user who is banned.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the moderator who issued the ban.
     */
    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }

    /**
     * Check if the ban is currently active.
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
     * Check if the ban is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
