<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\SubCommentCreated;
use App\Models\Comment;
use Illuminate\Support\Facades\Cache;

final class CommentObserver
{
    /**
     * Handle the Comment "created" event.
     */
    public function created(Comment $comment): void
    {
        if ($comment->post && $comment->post->sub_id && $comment->post->sub) {
            event(new SubCommentCreated($comment->post->sub, $comment));
        }

        $this->clearCommentCaches($comment);
    }

    /**
     * Handle the Comment "deleted" event.
     */
    public function deleted(Comment $comment): void
    {
        $this->clearCommentCaches($comment);
    }

    /**
     * Clear caches related to comments.
     */
    private function clearCommentCaches(Comment $comment): void
    {
        // Clear the post's specific cache (comment counts are embedded)
        if ($comment->post_id) {
            Cache::forget("post_{$comment->post_id}");

            // If we have the post loaded, also clear by slug
            if ($comment->post) {
                Cache::forget("post_slug_{$comment->post->slug}");
            }
        }

        // Clear activity feed caches (comments appear in feeds)
        Cache::tags(['activity'])->flush();
    }
}
