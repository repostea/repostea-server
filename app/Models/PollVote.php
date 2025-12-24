<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $post_id
 * @property int $option_number
 * @property int|null $user_id
 * @property string|null $device_fingerprint
 * @property string|null $ip_address
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Post $post
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollVote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollVote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollVote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollVote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollVote whereDeviceFingerprint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollVote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollVote whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollVote whereOptionNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollVote wherePostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollVote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollVote whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class PollVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'option_number',
        'user_id',
        'device_fingerprint',
        'ip_address',
    ];

    protected $casts = [
        'option_number' => 'integer',
    ];

    /**
     * Get the post that owns the poll vote.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get the user that owns the poll vote.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
