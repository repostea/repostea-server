<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $sub_id
 * @property bool $is_owner
 * @property int|null $added_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $addedBy
 * @property-read Sub $sub
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubModerator newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubModerator newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubModerator query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubModerator whereAddedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubModerator whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubModerator whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubModerator whereIsOwner($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubModerator whereSubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubModerator whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubModerator whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class SubModerator extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sub_id',
        'is_owner',
        'added_by',
    ];

    protected $casts = [
        'is_owner' => 'boolean',
    ];

    /**
     * Get the user who is a moderator.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the sub being moderated.
     */
    public function sub(): BelongsTo
    {
        return $this->belongsTo(Sub::class);
    }

    /**
     * Get the user who added this moderator.
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
