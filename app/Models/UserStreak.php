<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $current_streak
 * @property int $longest_streak
 * @property \Illuminate\Support\Carbon|null $last_activity_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $karma_multiplier
 * @property-read User $user
 *
 * @method static \Database\Factories\UserStreakFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStreak newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStreak newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStreak query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStreak whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStreak whereCurrentStreak($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStreak whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStreak whereLastActivityDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStreak whereLongestStreak($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStreak whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStreak whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class UserStreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'current_streak',
        'longest_streak',
        'last_activity_date',
    ];

    protected $casts = [
        'last_activity_date' => 'date',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate karma multiplier based on streak.
     */
    public function getKarmaMultiplierAttribute(): float
    {
        if ($this->current_streak >= 365) {
            return 3.0;
        } elseif ($this->current_streak >= 90) {
            return 2.0;
        } elseif ($this->current_streak >= 30) {
            return 1.5;
        } elseif ($this->current_streak >= 7) {
            return 1.2;
        }

        return 1.0;
    }
}
