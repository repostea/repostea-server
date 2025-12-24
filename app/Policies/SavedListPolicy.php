<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SavedList;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class SavedListPolicy
{
    use HandlesAuthorization;

    public function view(User $user, SavedList $savedList)
    {
        return $savedList->is_public || $user->id === $savedList->user_id || $user->isAdmin();
    }

    public function create(User $user)
    {
        return true;
    }

    public function update(User $user, SavedList $savedList)
    {
        return $user->id === $savedList->user_id || $user->isAdmin();
    }

    public function delete(User $user, SavedList $savedList)
    {
        if (in_array($savedList->type, ['favorite', 'read_later'], true)) {
            return false;
        }

        return $user->id === $savedList->user_id || $user->isAdmin();
    }
}
