<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $available_seals
 * @property int $total_earned
 * @property int $total_used
 * @property \Illuminate\Support\Carbon|null $last_awarded_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSeal newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSeal newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSeal query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSeal whereAvailableSeals($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSeal whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSeal whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSeal whereLastAwardedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSeal whereTotalEarned($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSeal whereTotalUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSeal whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSeal whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class UserSeal extends Model
{
    protected $fillable = [
        'user_id',
        'available_seals',
        'total_earned',
        'total_used',
        'last_awarded_at',
    ];

    protected $casts = [
        'last_awarded_at' => 'datetime',
    ];

    /**
     * Get the user that owns the seals.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user has enough seals.
     */
    public function hasSeals(int $amount = 1): bool
    {
        return $this->available_seals >= $amount;
    }

    /**
     * Use seals (decrement available seals).
     */
    public function useSeals(int $amount = 1): bool
    {
        if (! $this->hasSeals($amount)) {
            return false;
        }

        $this->available_seals -= $amount;
        $this->total_used += $amount;
        $this->save();

        return true;
    }

    /**
     * Award seals (increment available seals).
     */
    public function awardSeals(int $amount): void
    {
        $this->available_seals += $amount;
        $this->total_earned += $amount;
        $this->last_awarded_at = now();
        $this->save();
    }

    /**
     * Remove expired seals (older than 14 days / 2 weeks).
     */
    public function removeExpiredSeals(): int
    {
        if (! $this->last_awarded_at) {
            return 0;
        }

        $daysOld = now()->diffInDays($this->last_awarded_at);

        if ($daysOld >= 14 && $this->available_seals > 0) {
            $expired = $this->available_seals;
            $this->available_seals = 0;
            $this->save();

            return $expired;
        }

        return 0;
    }
}
