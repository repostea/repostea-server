<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $required_karma
 * @property string|null $badge
 * @property string|null $description
 * @property array<array-key, mixed>|null $benefits
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\KarmaLevelFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaLevel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaLevel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaLevel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaLevel whereBadge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaLevel whereBenefits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaLevel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaLevel whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaLevel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaLevel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaLevel whereRequiredKarma($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KarmaLevel whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class KarmaLevel extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'required_karma',
        'badge',
        'description',
        'benefits',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'required_karma' => 'integer',
        'benefits' => 'array',
    ];

    /**
     * Get users with this level as their highest level.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'highest_level_id');
    }
}
