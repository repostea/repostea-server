<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name_key
 * @property string $slug
 * @property string|null $icon
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 *
 * @method static \Database\Factories\TagCategoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TagCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TagCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TagCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TagCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TagCategory whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TagCategory whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TagCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TagCategory whereNameKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TagCategory whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TagCategory whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class TagCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_key',
        'slug',
        'description',
        'icon',
    ];

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class, 'tag_category_id');
    }
}
