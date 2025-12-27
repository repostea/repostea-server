<?php

declare(strict_types=1);

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $votable_type
 * @property int $votable_id
 * @property int $value
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Model|Eloquent $votable
 *
 * @method static \Database\Factories\VoteFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereVotableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereVotableType($value)
 *
 * @mixin \Eloquent
 */
final class Vote extends Model
{
    use HasFactory;

    public const VALUE_NEUTRAL = 0;

    public const VALUE_POSITIVE = 1;

    public const VALUE_NEGATIVE = -1;

    public const TYPE_DIDACTIC = 'didactic';

    public const TYPE_INTERESTING = 'interesting';

    public const TYPE_ELABORATE = 'elaborate';

    public const TYPE_FUNNY = 'funny';

    public const TYPE_INCOMPLETE = 'incomplete';

    public const TYPE_IRRELEVANT = 'irrelevant';

    public const TYPE_FALSE = 'false';

    public const TYPE_OUTOFPLACE = 'outofplace';

    protected $fillable = [
        'user_id',
        'votable_id',
        'votable_type',
        'value',
        'type',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function votable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string>
     */
    public static function getValidPositiveTypes(): array
    {
        return [
            self::TYPE_DIDACTIC,
            self::TYPE_INTERESTING,
            self::TYPE_ELABORATE,
            self::TYPE_FUNNY,
        ];
    }

    /**
     * @return array<string>
     */
    public static function getValidNegativeTypes(): array
    {
        return [
            self::TYPE_INCOMPLETE,
            self::TYPE_IRRELEVANT,
            self::TYPE_FALSE,
            self::TYPE_OUTOFPLACE,
        ];
    }

    public static function isValidType(int $value, string $type): bool
    {
        if ($value === self::VALUE_POSITIVE) {
            return in_array($type, self::getValidPositiveTypes(), true);
        } elseif ($value === self::VALUE_NEGATIVE) {
            return in_array($type, self::getValidNegativeTypes(), true);
        }

        return false;
    }
}
