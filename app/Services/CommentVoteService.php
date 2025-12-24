<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Comment;
use App\Models\Vote;
use Illuminate\Support\Facades\Auth;

/**
 * Handles voting operations on comments.
 */
final class CommentVoteService
{
    /**
     * @return array{message: string, success: bool, user_vote?: int, user_vote_type?: string|null}
     */
    public function voteComment(Comment $comment, int $value, ?string $type = null): array
    {
        if (! $this->isValidVoteType($value, $type)) {
            return [
                'message' => __('votes.invalid_type'),
                'success' => false,
            ];
        }

        $userId = Auth::id();
        $existingVote = $comment->votes()->where('user_id', $userId)->first();

        if ($existingVote) {
            if ($existingVote->value === $value && $existingVote->type === $type) {
                return [
                    'message' => __('messages.votes.already_voted'),
                    'success' => false,
                ];
            }

            $existingVote->update([
                'value' => $value,
                'type' => $type,
            ]);
            $message = __('messages.votes.updated');
        } else {
            $comment->votes()->create([
                'user_id' => $userId,
                'value' => $value,
                'type' => $type,
            ]);
            $message = __('messages.votes.recorded');
        }

        $comment->updateVotesCount();
        $comment->load('votes.user');

        return [
            'message' => $message,
            'user_vote' => $value,
            'user_vote_type' => $type,
            'success' => true,
        ];
    }

    /**
     * @return array{message: string, success: bool}
     */
    public function unvoteComment(Comment $comment): array
    {
        $userId = Auth::id();
        $comment->votes()->where('user_id', $userId)->delete();
        $comment->updateVotesCount();
        $comment->load('votes.user');

        return [
            'message' => __('messages.votes.removed'),
            'success' => true,
        ];
    }

    /**
     * @return array{votes_count: int, vote_details: array<int, array{id: int, user_id: int, username: string, value: int, type: string|null}>, vote_types: array<string, int>}
     */
    public function getVoteStats(Comment $comment): array
    {
        $comment->load('votes.user');
        $votes = $comment->votes;

        $voteTypes = [];
        foreach (Vote::getValidPositiveTypes() as $type) {
            $voteTypes[$type] = $votes->where('value', Vote::VALUE_POSITIVE)
                ->where('type', $type)
                ->count();
        }

        foreach (Vote::getValidNegativeTypes() as $type) {
            $voteTypes[$type] = $votes->where('value', Vote::VALUE_NEGATIVE)
                ->where('type', $type)
                ->count() * -1;
        }

        $upvotes = $votes->where('value', Vote::VALUE_POSITIVE)->count();
        $downvotes = $votes->where('value', Vote::VALUE_NEGATIVE)->count();

        return [
            'votes_count' => $upvotes - $downvotes,
            'vote_details' => $votes->map(static fn ($vote) => [
                'id' => $vote->id,
                'user_id' => $vote->user_id,
                'username' => $vote->user->username,
                'value' => $vote->value,
                'type' => $vote->type,
            ])->toArray(),
            'vote_types' => $voteTypes,
        ];
    }

    public function isValidVoteType(int $value, ?string $type): bool
    {
        if ($type === null) {
            return true;
        }

        return Vote::isValidType($value, $type);
    }

    /**
     * @return array<int, string>
     */
    public function getValidVoteTypes(int $value): array
    {
        return $value === Vote::VALUE_POSITIVE
            ? Vote::getValidPositiveTypes()
            : Vote::getValidNegativeTypes();
    }
}
