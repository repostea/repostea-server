<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Achievement;
use App\Models\User;

final class AchievementPolicy
{
    /**
     * Determine if the user can view any achievements.
     */
    public function viewAny(?User $user): bool
    {
        return true; // Achievements are visible to everyone
    }

    /**
     * Determine if the user can view the achievement.
     */
    public function view(?User $user, Achievement $achievement): bool
    {
        return true; // Individual achievements are visible to everyone
    }

    /**
     * Determine if the user can create achievements.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin(); // Only admins can create achievements
    }

    /**
     * Determine if the user can update the achievement.
     */
    public function update(User $user, Achievement $achievement): bool
    {
        return $user->isAdmin(); // Only admins can edit achievements
    }

    /**
     * Determine if the user can delete the achievement.
     */
    public function delete(User $user, Achievement $achievement): bool
    {
        return $user->isAdmin(); // Only admins can delete achievements
    }
}
