<?php

declare(strict_types=1);

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $amount
 * @property string $source
 * @property int|null $source_id
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|Eloquent $sourceable
 * @property-read User $user
 *
 * @method static \Database\Factories\KarmaHistoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaHistory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaHistory whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaHistory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaHistory whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaHistory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaHistory whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaHistory whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaHistory whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaHistory whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class KarmaHistory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'amount',
        'source',
        'source_id',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'integer',
    ];

    /**
     * Get the user this karma record belongs to.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the source entity of this karma (optional, polymorphic).
     */
    public function sourceable()
    {
        return $this->morphTo();
    }

    /**
     * Record a new karma transaction.
     *
     * @param  User  $user  The user receiving karma
     * @param  int  $amount  Karma amount (positive or negative)
     * @param  string  $source  Karma source (post, comment, streak, achievement, etc)
     * @param  int|null  $sourceId  Related entity ID (optional)
     * @param  string|null  $description  Additional description
     *
     * @return KarmaHistory
     */
    public static function record(User $user, int $amount, string $source, $sourceId = null, $description = null)
    {
        return self::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'source' => $source,
            'source_id' => $sourceId,
            'description' => $description,
        ]);
    }
}
