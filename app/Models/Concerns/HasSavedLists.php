<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\SavedList;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trait for saved lists functionality (favorites, read later, custom lists).
 *
 * @property int $id
 */
trait HasSavedLists
{
    public function savedLists(): HasMany
    {
        return $this->hasMany(SavedList::class);
    }

    public function getFavoritesListAttribute(): SavedList
    {
        $list = $this->savedLists()->where('type', 'favorite')
            ->where('user_id', $this->id)
            ->first();

        if (! $list) {
            $list = $this->savedLists()->create([
                'name' => 'Favorites',
                'type' => 'favorite',
                'is_public' => false,
                'slug' => 'favorites',
            ]);
        }

        return $list;
    }

    public function getReadLaterListAttribute(): SavedList
    {
        $list = $this->savedLists()->where('type', 'read_later')
            ->where('user_id', $this->id)
            ->first();

        if (! $list) {
            $list = $this->savedLists()->create([
                'name' => 'read-later',
                'type' => 'read_later',
                'is_public' => false,
                'slug' => 'read-later',
            ]);
        }

        return $list;
    }

    public function hasFavorite(int $postId): bool
    {
        return $this->favorites_list->posts()->where('post_id', $postId)->exists();
    }

    public function hasReadLater(int $postId): bool
    {
        return $this->read_later_list->posts()->where('post_id', $postId)->exists();
    }

    public function hasSavedInList(int $postId, int $listId): bool
    {
        return $this->savedLists()
            ->where('id', $listId)
            ->whereHas('posts', static function ($query) use ($postId): void {
                $query->where('post_id', $postId);
            })
            ->exists();
    }
}
