<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use App\Models\Vote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Handles voting operations on posts.
 */
final class PostVoteService
{
    public function __construct(
        private readonly RealtimeBroadcastService $realtimeService,
    ) {}

    /**
     * @return array{message: string, votes: int, updated: bool, frontpage_reached?: bool}
     */
    public function votePost(Post $post, int $value, string $type): array
    {
        // Validate that the type is appropriate for the value
        if (! Vote::isValidType($value, $type)) {
            throw new InvalidArgumentException('Invalid vote type for the given value');
        }

        $userId = Auth::id();
        $existingVote = $post->votes()->where('user_id', $userId)->first();

        if ($existingVote) {
            if ($existingVote->value === $value && $existingVote->type === $type) {
                return [
                    'message' => __('messages.votes.already_voted'),
                    'votes' => $post->votes_count,
                    'updated' => false,
                ];
            }

            $existingVote->update([
                'value' => $value,
                'type' => $type,
            ]);
            $message = __('messages.votes.updated');
        } else {
            $post->votes()->create([
                'user_id' => $userId,
                'value' => $value,
                'type' => $type,
            ]);
            $message = __('messages.votes.recorded');
        }

        // Store previous frontpage_at to detect if post reached frontpage
        $previousFrontpageAt = $post->frontpage_at;

        $post->updateVotesCount();

        // Check if post just reached frontpage
        $frontpageReached = $previousFrontpageAt === null && $post->frontpage_at !== null;

        // Queue realtime broadcast
        $this->realtimeService->queueVoteChange($post, $value);

        // Note: Cache invalidation happens automatically via PostObserver
        // when votes_count changes (including frontpage_at updates)

        return [
            'message' => $message,
            'votes' => $post->votes_count,
            'updated' => true,
            'frontpage_reached' => $frontpageReached,
        ];
    }

    /**
     * @return array{message: string, votes: int}
     */
    public function unvotePost(Post $post): array
    {
        $userId = Auth::id();
        $post->votes()->where('user_id', $userId)->delete();
        $post->updateVotesCount();

        // Queue realtime broadcast
        $this->realtimeService->queueVoteChange($post, 0);

        return [
            'message' => __('messages.votes.removed'),
            'votes' => Vote::VALUE_NEUTRAL,
        ];
    }

    /**
     * @return array{vote_types: array<string, int>, total_upvotes: int, total_votes: int, vote_score: int}
     */
    public function getVoteStats(Post $post): array
    {
        $votes = $post->votes();

        $totalUpvotes = $votes->where('value', Vote::VALUE_POSITIVE)->count();

        return [
            'vote_types' => [],
            'total_upvotes' => $totalUpvotes,
            'total_votes' => $totalUpvotes,
            'vote_score' => $totalUpvotes,
        ];
    }
}
