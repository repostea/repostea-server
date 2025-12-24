<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sub_id
 * @property int $user_id
 * @property int|null $banned_by
 * @property string $reason
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $bannedBy
 * @property-read Sub $sub
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubBan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubBan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubBan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubBan whereBannedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubBan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubBan whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubBan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubBan whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubBan whereSubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubBan whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubBan whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class SubBan extends Model
{
    use HasFactory;

    protected $fillable = [
        'sub_id',
        'user_id',
        'banned_by',
        'reason',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the sub where the user is banned.
     */
    public function sub(): BelongsTo
    {
        return $this->belongsTo(Sub::class);
    }

    /**
     * Get the banned user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
