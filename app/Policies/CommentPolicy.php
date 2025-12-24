<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class CommentPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Comment $comment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Comment $comment): Response
    {
        // Admins can always edit
        if ($user->isAdmin()) {
            return Response::allow();
        }

        // Authors can only edit their own comments
        if ($user->id !== $comment->user_id) {
            return Response::deny('You cannot edit this comment.');
        }

        // Check if comment was created within the last 15 minutes
        $minutesSinceCreation = $comment->created_at->diffInMinutes(now());
        if ($minutesSinceCreation > 15) {
            return Response::deny('You can only edit comments within 15 minutes of posting.');
        }

        return Response::allow();
    }

    public function delete(User $user, Comment $comment): Response
    {
        // Users can delete their own comments
        if ($user->id === $comment->user_id) {
            return Response::allow();
        }

        // Post authors can delete comments on their posts
        $post = $comment->post()->first();
        if ($post !== null && $user->id === $post->user_id) {
            return Response::allow();
        }

        // Admins can always delete
        if ($user->isAdmin()) {
            return Response::allow();
        }

        // Sub moderators can delete comments in their subs (including remote comments)
        if ($post !== null && $post->sub !== null) {
            if ($post->sub->isModerator($user)) {
                return Response::allow();
            }
        }

        return Response::deny('You cannot delete this comment.');
    }

    /**
     * Determine if the user can moderate comments (hide, delete, etc.).
     * This is for remote comments and moderation actions.
     */
    public function moderate(User $user, Comment $comment): Response
    {
        // Admins can moderate all comments
        if ($user->isAdmin()) {
            return Response::allow();
        }

        // Sub moderators can moderate comments in their subs
        $post = $comment->post()->first();
        if ($post !== null && $post->sub !== null) {
            if ($post->sub->isModerator($user)) {
                return Response::allow();
            }
        }

        // Post authors can moderate comments on their posts
        if ($post !== null && $user->id === $post->user_id) {
            return Response::allow();
        }

        return Response::deny('You cannot moderate this comment.');
    }

    public function vote(User $user, Comment $comment): bool
    {
        return $user->id !== $comment->user_id;
    }
}
