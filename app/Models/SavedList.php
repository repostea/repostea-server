<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $user_id
 * @property bool $is_public
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Post> $posts
 * @property-read int|null $posts_count
 * @property-read User $user
 *
 * @method static \Database\Factories\SavedListFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedList newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedList newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedList query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedList whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedList whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedList whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedList whereIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedList whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedList whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedList whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedList whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedList whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedList whereUuid($value)
 *
 * @mixin \Eloquent
 */
final class SavedList extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'is_public',
        'type',
        'slug',
        'uuid',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        self::creating(static function ($list): void {
            if (empty($list->uuid)) {
                $list->uuid = (string) Str::uuid();
            }

            if (empty($list->slug)) {
                if (in_array($list->type, ['favorite', 'read_later'], true)) {
                    $list->slug = $list->type === 'favorite' ? 'favorites' : 'read-later';
                } else {
                    $baseSlug = Str::slug($list->name);
                    $slug = $baseSlug;
                    $counter = 1;

                    while (self::where('user_id', $list->user_id)
                        ->where('slug', $slug)
                        ->exists()) {
                        $slug = $baseSlug . '-' . $counter;
                        $counter++;
                    }

                    $list->slug = $slug;
                }
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return $this->type === 'custom' ? 'uuid' : 'slug';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'saved_list_posts')
            ->withPivot('notes')
            ->withTimestamps();
    }
}
