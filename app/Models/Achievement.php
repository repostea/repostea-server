<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $description
 * @property string|null $icon
 * @property string $type
 * @property array<array-key, mixed> $requirements
 * @property int $karma_bonus
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $translated_description
 * @property-read mixed $translated_name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\AchievementFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement whereKarmaBonus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement whereRequirements($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Achievement whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class Achievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'type',
        'requirements',
        'karma_bonus',
    ];

    protected $casts = [
        'requirements' => 'array',
    ];

    protected $appends = ['translated_name', 'translated_description'];

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('progress', 'unlocked_at')
            ->withTimestamps();
    }

    /**
     * Get the translated name attribute.
     */
    protected function translatedName(): Attribute
    {
        return Attribute::make(
            get: fn () => __($this->attributes['name']),
        );
    }

    /**
     * Get the translated description attribute.
     */
    protected function translatedDescription(): Attribute
    {
        return Attribute::make(
            get: fn () => __($this->attributes['description']),
        );
    }
}
