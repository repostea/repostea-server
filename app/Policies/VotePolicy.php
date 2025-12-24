<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vote;
use Illuminate\Auth\Access\Response;

final class VotePolicy
{
    /**
     * Determine if the user can view any votes.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin(); // Only admins can view all votes
    }

    /**
     * Determine if the user can view the vote.
     */
    public function view(User $user, Vote $vote): bool
    {
        // User can view their own vote or admin can view any vote
        return $user->id === $vote->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can create votes.
     */
    public function create(User $user): bool
    {
        return true; // Authenticated users can vote
    }

    /**
     * Determine if the user can update the vote.
     */
    public function update(User $user, Vote $vote): Response
    {
        // User can only update their own vote
        if ($user->id !== $vote->user_id) {
            return Response::deny(__('messages.votes.cannot_update_others'));
        }

        return Response::allow();
    }

    /**
     * Determine if the user can delete the vote.
     */
    public function delete(User $user, Vote $vote): Response
    {
        // User can only delete their own vote
        if ($user->id !== $vote->user_id) {
            return Response::deny(__('messages.votes.cannot_delete_others'));
        }

        return Response::allow();
    }
}
