<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $category
 * @property \Illuminate\Support\Carbon|null $last_viewed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationViewTimestamp newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationViewTimestamp newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationViewTimestamp query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationViewTimestamp whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationViewTimestamp whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationViewTimestamp whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationViewTimestamp whereLastViewedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationViewTimestamp whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationViewTimestamp whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class NotificationViewTimestamp extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'last_viewed_at',
    ];

    protected $casts = [
        'last_viewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
