<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $date
 * @property int $karma_earned
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyKarmaStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyKarmaStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyKarmaStat query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyKarmaStat whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyKarmaStat whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyKarmaStat whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyKarmaStat whereKarmaEarned($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyKarmaStat whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyKarmaStat whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class DailyKarmaStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'karma_earned',
    ];

    protected $casts = [
        'date' => 'date',
        'karma_earned' => 'integer',
    ];

    /**
     * Get the user that owns the karma stat.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
