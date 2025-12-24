<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\SubContentUpvoted;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Vote;
use Illuminate\Support\Facades\Cache;

final class VoteObserver
{
    /**
     * Handle the Vote "created" event.
     */
    public function created(Vote $vote): void
    {
        $this->updateVotesCount($vote);
        $this->dispatchSubUpvoteEvent($vote);
    }

    /**
     * Handle the Vote "updated" event.
     */
    public function updated(Vote $vote): void
    {
        $this->updateVotesCount($vote);
    }

    /**
     * Handle the Vote "deleted" event.
     */
    public function deleted(Vote $vote): void
    {
        $this->updateVotesCount($vote);
    }

    /**
     * Update the votes count for the votable model.
     */
    private function updateVotesCount(Vote $vote): void
    {
        $votable = $vote->votable;

        if (! $votable) {
            return;
        }

        if ($votable instanceof Post) {
            $votable->updateVotesCount();
            $this->clearUserPendingCache($vote->user_id);
        } elseif ($votable instanceof Comment) {
            $votable->updateVotesCount();
        }
    }

    /**
     * Clear the user's pending posts count cache.
     */
    private function clearUserPendingCache(?int $userId): void
    {
        if ($userId !== null) {
            Cache::tags(["user_{$userId}_pending"])->flush();
        }
    }

    /**
     * Dispatch subcommunity upvote event if applicable.
     */
    private function dispatchSubUpvoteEvent(Vote $vote): void
    {
        // Only process upvotes (choice = 1)
        if ($vote->choice !== 1) {
            return;
        }

        $votable = $vote->votable;

        if (! $votable) {
            return;
        }

        if ($votable instanceof Post && $votable->sub_id && $votable->sub) {
            event(new SubContentUpvoted($votable->sub, $votable));
        }

        if ($votable instanceof Comment && $votable->post && $votable->post->sub_id && $votable->post->sub) {
            event(new SubContentUpvoted($votable->post->sub, $votable));
        }
    }
}
