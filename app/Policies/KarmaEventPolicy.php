<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KarmaEvent;
use App\Models\User;

final class KarmaEventPolicy
{
    /**
     * Determine if the user can view any karma events.
     */
    public function viewAny(?User $user): bool
    {
        return true; // Karma events are visible to everyone
    }

    /**
     * Determine if the user can view the karma event.
     */
    public function view(?User $user, KarmaEvent $karmaEvent): bool
    {
        return true; // Individual karma events are visible to everyone
    }

    /**
     * Determine if the user can create karma events.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin(); // Only admins can create karma events
    }

    /**
     * Determine if the user can update the karma event.
     */
    public function update(User $user, KarmaEvent $karmaEvent): bool
    {
        return $user->isAdmin(); // Only admins can edit karma events
    }

    /**
     * Determine if the user can delete the karma event.
     */
    public function delete(User $user, KarmaEvent $karmaEvent): bool
    {
        return $user->isAdmin(); // Only admins can delete karma events
    }
}
