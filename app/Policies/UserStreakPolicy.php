<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\UserStreak;

final class UserStreakPolicy
{
    /**
     * Determine if the user can view any streaks.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin(); // Only administrators can see all streaks
    }

    /**
     * Determine if the user can view the streak.
     */
    public function view(?User $user, UserStreak $streak): bool
    {
        // Streaks are public, anyone can view them
        return true;
    }

    /**
     * Determine if the user can update the streak.
     */
    public function update(User $user, UserStreak $streak): bool
    {
        return $user->isAdmin(); // Only administrators can manually update streaks
    }

    /**
     * Determine if the user can reset the streak.
     */
    public function reset(User $user, UserStreak $streak): bool
    {
        // A user can reset their own streak or administrators can reset any streak
        return $user->id === $streak->user_id || $user->isAdmin();
    }
}
