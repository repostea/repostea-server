<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class PostPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Post $post): bool
    {
        if ($post->status === 'published') {
            return true;
        }

        return $user !== null && $user->id === $post->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Post $post): Response
    {
        if ($user->id === $post->user_id) {
            return Response::allow();
        }
        if ($user->isAdmin()) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to edit this post.');
    }

    public function delete(User $user, Post $post): Response
    {
        if ($user->id === $post->user_id) {
            if ($post->comment_count > 0) {
                // Allow deletion if post is less than 24 hours old
                $hoursLimit = 24;
                $hoursSinceCreation = $post->created_at->diffInHours(now());

                if ($hoursSinceCreation >= $hoursLimit) {
                    return Response::deny(__('messages.posts.cannot_delete_with_comments_after_hours', ['hours' => $hoursLimit]));
                }
            }

            return Response::allow();
        }
        if ($user->isAdmin()) {
            return Response::allow();
        }

        return Response::deny(__('messages.posts.no_permission_to_delete'));
    }

    public function restore(User $user, Post $post): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $user->isAdmin();
    }

    public function vote(User $user, Post $post): bool
    {
        return $user->id !== $post->user_id;
    }
}
