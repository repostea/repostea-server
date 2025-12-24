<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

final class UserPolicy
{
    /**
     * Determine if the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin(); // Only administrators can see the user list
    }

    /**
     * Determine if the user can view the model.
     */
    public function view(?User $user, User $model): bool
    {
        // Any user can view public profiles
        return true;
    }

    /**
     * Determine if the user can update the model.
     */
    public function update(User $user, User $model): Response
    {
        // Users can only update their own profile
        if ($user->id === $model->id) {
            return Response::allow();
        }

        // Administrators can update any profile
        if ($user->isAdmin()) {
            return Response::allow();
        }

        return Response::deny('You cannot edit another user\'s profile.');
    }

    /**
     * Determine if the user can delete the model.
     */
    public function delete(User $user, User $model): Response
    {
        // Users can delete their own account
        if ($user->id === $model->id) {
            return Response::allow();
        }

        // Administrators can delete any account except other administrator accounts
        if ($user->isAdmin()) {
            if ($model->isAdmin() && $user->id !== $model->id) {
                return Response::deny('You cannot delete another administrator\'s account.');
            }

            return Response::allow();
        }

        return Response::deny('You do not have permission to delete this account.');
    }

    /**
     * Determine if the user can view the karma details of the model.
     */
    public function viewKarmaDetails(User $user, User $model): bool
    {
        // Users can only view their own complete karma details
        // or administrators can view all
        return $user->id === $model->id || $user->isAdmin();
    }

    /**
     * Determine if the user can view the achievements of the model.
     */
    public function viewAchievements(User $user, User $model): bool
    {
        // Achievements are visible to everyone
        return true;
    }
}
