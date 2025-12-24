<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KarmaHistory;
use App\Models\User;

final class KarmaHistoryPolicy
{
    /**
     * Determine if the user can view any karma history entries.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin(); // Only admins can view all karma history
    }

    /**
     * Determine if the user can view the karma history entry.
     */
    public function view(User $user, KarmaHistory $karmaHistory): bool
    {
        // User can only view their own karma history entries
        // Admins can view any entry
        return $user->id === $karmaHistory->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can create karma history entries.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin(); // Only admins can create history entries manually
    }

    /**
     * Determine if the user can update the karma history entry.
     */
    public function update(User $user, KarmaHistory $karmaHistory): bool
    {
        return $user->isAdmin(); // Only admins can edit history entries
    }

    /**
     * Determine if the user can delete the karma history entry.
     */
    public function delete(User $user, KarmaHistory $karmaHistory): bool
    {
        return $user->isAdmin(); // Only admins can delete history entries
    }
}
