<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $post_id
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $last_visited_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Post $post
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostView newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostView newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostView query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostView whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostView whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostView whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostView whereLastVisitedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostView wherePostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostView whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostView whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostView whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class PostView extends Model
{
    protected $fillable = [
        'post_id',
        'user_id',
        'ip_address',
        'user_agent',
        'last_visited_at',
    ];

    protected $casts = [
        'last_visited_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
