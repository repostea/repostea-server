<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KarmaLevel;
use App\Models\User;

final class KarmaLevelPolicy
{
    /**
     * Determine if the user can view any karma levels.
     */
    public function viewAny(?User $user): bool
    {
        return true; // Karma levels are visible to everyone
    }

    /**
     * Determine if the user can view the karma level.
     */
    public function view(?User $user, KarmaLevel $karmaLevel): bool
    {
        return true; // Individual karma levels are visible to everyone
    }

    /**
     * Determine if the user can create karma levels.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin(); // Only admins can create karma levels
    }

    /**
     * Determine if the user can update the karma level.
     */
    public function update(User $user, KarmaLevel $karmaLevel): bool
    {
        return $user->isAdmin(); // Only admins can edit karma levels
    }

    /**
     * Determine if the user can delete the karma level.
     */
    public function delete(User $user, KarmaLevel $karmaLevel): bool
    {
        return $user->isAdmin(); // Only admins can delete karma levels
    }
}
