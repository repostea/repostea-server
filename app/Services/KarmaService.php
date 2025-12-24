<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AgoraMessage;
use App\Models\AgoraVote;
use App\Models\Comment;
use App\Models\KarmaEvent;
use App\Models\Post;
use App\Models\PostRelationship;
use App\Models\User;
use App\Models\Vote;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final class KarmaService implements KarmaServiceInterface
{
    private const BASE_POST_KARMA = 10;

    private const BASE_COMMENT_KARMA = 5;

    private const BASE_AGORA_KARMA = 5;

    private const COMMENT_DOWNVOTE_PENALTY = 1;

    private const AGORA_DOWNVOTE_PENALTY = 1;

    private const MAX_POST_KARMA = 500;

    private const MAX_POST_COMMENT_BONUS = 25;

    private const MAX_COMMENT_REPLY_BONUS = 12;

    private const MAX_ACTIVITY_BONUS = 50;

    private const DECAY_THRESHOLD_VOTES = 10;

    private const DECAY_LOG_BASE = 11;

    private const BASE_RELATIONSHIP_KARMA = 3;

    private const RELATIONSHIP_VOTE_MULTIPLIER = 0.5;

    private const MAX_RELATIONSHIP_KARMA = 15;

    private const MIN_VOTES_FOR_KARMA = 2;

    public function recordActivity(User $user)
    {
        return app(StreakService::class)->recordActivity($user);
    }

    public function updateUserKarma(User $user)
    {
        try {
            $totalKarma = $this->calculateTotalUserKarma($user);
            $karmaChange = $totalKarma - $user->karma_points;

            if ($karmaChange !== 0) {
                $user->updateKarma($karmaChange);

                if ($karmaChange > 0) {
                    $user->recordKarma($karmaChange, 'recalculation', null, 'Periodic karma recalculation');
                }
            }

            return $user;
        } catch (Exception $e) {
            Log::error('Error updating user karma', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function processVoteKarma(Vote $vote)
    {
        try {
            $content = $vote->getRelationValue('votable');
            if ($content === null) {
                return;
            }

            $contentOwner = $content->user()->first();
            $voter = $vote->user()->first();

            if (! $this->canProcessVote($contentOwner, $voter)) {
                return;
            }

            $karmaChange = $this->calculateVoteKarmaChange($vote, $content, $voter);

            if ($karmaChange !== 0) {
                $this->applyKarmaChange($contentOwner, $karmaChange, $vote, $content);
            }

            return $contentOwner;
        } catch (Exception $e) {
            Log::error('Error processing vote karma', [
                'vote_id' => $vote->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }
    }

    /**
     * Process karma for Agora message votes.
     * Same logic as comments: +5 for upvote, -1 for downvote.
     */
    public function processAgoraVoteKarma(AgoraVote $vote, ?int $previousValue = null): void
    {
        try {
            $message = AgoraMessage::find($vote->agora_message_id);
            if ($message === null) {
                return;
            }

            $messageOwner = User::find($message->user_id);
            $voter = User::find($vote->user_id);

            // Don't give karma for own votes
            if ($messageOwner === null || $voter === null || $messageOwner->id === $voter->id) {
                return;
            }

            $karmaChange = 0;

            // If there was a previous vote, reverse it first
            if ($previousValue !== null) {
                if ($previousValue === 1) {
                    $karmaChange -= self::BASE_AGORA_KARMA;
                } elseif ($previousValue === -1) {
                    $karmaChange += self::AGORA_DOWNVOTE_PENALTY;
                }
            }

            // Apply new vote karma
            if ($vote->value === 1) {
                $karmaChange += self::BASE_AGORA_KARMA;
            } elseif ($vote->value === -1) {
                $karmaChange -= self::AGORA_DOWNVOTE_PENALTY;
            }

            if ($karmaChange !== 0) {
                $karmaChange = $this->applyEventMultipliers($karmaChange);
                $messageOwner->updateKarma($karmaChange);
                $messageOwner->recordKarma($karmaChange, 'agora_vote', $vote->id, 'Voto en mensaje del Ágora');
            }
        } catch (Exception $e) {
            Log::error('Error processing Agora vote karma', [
                'vote_id' => $vote->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reverse karma when an Agora vote is removed.
     */
    public function reverseAgoraVoteKarma(AgoraVote $vote): void
    {
        try {
            $message = AgoraMessage::find($vote->agora_message_id);
            if ($message === null) {
                return;
            }

            $messageOwner = User::find($message->user_id);
            $voter = User::find($vote->user_id);

            if ($messageOwner === null || $voter === null || $messageOwner->id === $voter->id) {
                return;
            }

            $karmaChange = 0;

            if ($vote->value === 1) {
                $karmaChange = -self::BASE_AGORA_KARMA;
            } elseif ($vote->value === -1) {
                $karmaChange = self::AGORA_DOWNVOTE_PENALTY;
            }

            if ($karmaChange !== 0) {
                $karmaChange = $this->applyEventMultipliers($karmaChange);
                $messageOwner->updateKarma($karmaChange);
                $messageOwner->recordKarma($karmaChange, 'agora_vote_removed', $vote->id, 'Voto eliminado en mensaje del Ágora');
            }
        } catch (Exception $e) {
            Log::error('Error reversing Agora vote karma', [
                'vote_id' => $vote->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function addKarma(User $user, int $amount, string $source, mixed $sourceId = null, mixed $description = null)
    {
        try {
            $finalAmount = $this->applyEventMultipliers($amount);
            $user->updateKarma($finalAmount);
            $user->recordKarma($finalAmount, $source, $sourceId, $description);

            return $user;
        } catch (Exception $e) {
            Log::error('Error adding karma', [
                'user_id' => $user->id,
                'amount' => $amount,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            return $user;
        }
    }

    private function calculateTotalUserKarma(User $user): int
    {
        $postsKarma = $this->calculateAllPostsKarma($user);
        $commentsKarma = $this->calculateAllCommentsKarma($user);
        $relationshipsKarma = $this->calculateAllRelationshipsKarma($user);
        $activityBonus = $this->calculateUserActivityBonus($user);

        return $postsKarma + $commentsKarma + $relationshipsKarma + $activityBonus;
    }

    private function calculateAllPostsKarma(User $user): int
    {
        $totalKarma = 0;

        // Optimized: Load all posts with vote and comment counts in a single query
        $posts = $user->posts()
            ->withCount([
                'votes as upvotes_count' => fn ($q) => $q->where('value', 1),
                'comments as comments_count',
            ])
            ->get();

        foreach ($posts as $post) {
            $postKarma = $this->calculatePostKarma(
                $post->upvotes_count,
                $post->comments_count,
                $post->created_at,
            );

            $totalKarma += $postKarma;
        }

        return max(0, $totalKarma);
    }

    private function calculateAllCommentsKarma(User $user): int
    {
        $totalKarma = 0;

        // Optimized: Load all comments with vote and reply counts in a single query
        $comments = $user->comments()
            ->withCount([
                'votes as upvotes_count' => fn ($q) => $q->where('value', 1),
                'votes as downvotes_count' => fn ($q) => $q->where('value', -1),
                'replies as replies_count',
            ])
            ->get();

        foreach ($comments as $comment) {
            $commentKarma = $this->calculateCommentKarma(
                $comment->upvotes_count,
                $comment->downvotes_count,
                $comment->replies_count,
                $comment->created_at,
            );

            $totalKarma += $commentKarma;
        }

        return max(0, $totalKarma);
    }

    private function calculateAllRelationshipsKarma(User $user): int
    {
        $totalKarma = 0;

        // Get all relationships created by this user
        $relationships = PostRelationship::where('created_by', $user->id)->get();

        foreach ($relationships as $relationship) {
            if ($relationship->created_at === null) {
                continue;
            }

            $relationshipKarma = $this->calculateRelationshipKarma(
                $relationship->upvotes_count,
                $relationship->downvotes_count,
                $relationship->created_at,
            );

            $totalKarma += $relationshipKarma;
        }

        return max(0, $totalKarma);
    }

    private function calculateUserActivityBonus(User $user): int
    {
        $recentPosts = $user->posts()->where('created_at', '>=', now()->subDays(30))->count();
        $recentComments = $user->comments()->where('created_at', '>=', now()->subDays(30))->count();
        $recentVotes = $user->votes()->where('created_at', '>=', now()->subDays(30))->count();

        return $this->calculateActivityBonus($recentPosts, $recentComments, $recentVotes);
    }

    private function canProcessVote(?User $contentOwner, ?User $voter): bool
    {
        if ($contentOwner === null || $voter === null) {
            return false;
        }

        return $contentOwner->id !== $voter->id;
    }

    private function calculateVoteKarmaChange(Vote $vote, mixed $content, User $voter): int
    {
        $baseKarma = $this->getBaseKarmaForVote($vote, $content);

        if ($baseKarma === 0) {
            return 0;
        }

        $withMultiplier = $this->applyLevelMultiplier($baseKarma, $voter);

        return $this->applyEventMultipliers($withMultiplier);
    }

    private function getBaseKarmaForVote(Vote $vote, mixed $content): int
    {
        if ($vote->value === 1) {
            return $this->getPositiveVoteKarma($content);
        }

        if ($vote->value === -1 && $content instanceof Comment) {
            return -1;
        }

        return 0;
    }

    private function getPositiveVoteKarma(mixed $content): int
    {
        return match (true) {
            $content instanceof Post => 10,
            $content instanceof Comment => 5,
            default => 0,
        };
    }

    private function applyLevelMultiplier(int $karma, User $voter): int
    {
        if (! $voter->currentLevel) {
            return $karma;
        }

        $multiplier = $this->getLevelMultiplierByKarma($voter->currentLevel->required_karma);

        return (int) round($karma * $multiplier);
    }

    private function getLevelMultiplierByKarma(int $requiredKarma): float
    {
        return match (true) {
            $requiredKarma >= 100000 => 1.15,
            $requiredKarma >= 40000 => 1.10,
            $requiredKarma >= 16000 => 1.05,
            default => 1.0,
        };
    }

    private function applyKarmaChange(User $contentOwner, int $karmaChange, Vote $vote, mixed $content): void
    {
        $contentOwner->updateKarma($karmaChange);

        $voteType = $vote->value === 1 ? 'Positive vote' : 'Negative vote';
        $contentType = $content instanceof Post ? 'post' : 'comment';
        $description = "{$voteType} on {$contentType} #{$content->id}";

        $contentOwner->recordKarma($karmaChange, 'vote', $vote->id, $description);
    }

    public function applyEventMultipliers(int $karma): int
    {
        if ($karma <= 0) {
            return $karma;
        }

        $activeEvents = KarmaEvent::getActiveEvents();

        if ($activeEvents->isEmpty()) {
            return $karma;
        }

        $finalKarma = $karma;

        foreach ($activeEvents as $event) {
            $multiplier = $event->getAttribute('multiplier');
            if ($multiplier !== null) {
                $finalKarma = (int) round($finalKarma * $multiplier);
            }
        }

        return (int) $finalKarma;
    }

    private function calculatePostKarma(int $upvotes, int $commentsCount, Carbon $createdAt): int
    {
        $baseKarma = $this->applyDiminishingReturns($upvotes, self::BASE_POST_KARMA);
        $timeMultiplier = $this->getTimeBasedMultiplier($createdAt);
        $postKarma = round($baseKarma * $timeMultiplier);

        $commentBonus = $this->calculateCommentBonus($commentsCount);
        $totalKarma = $postKarma + $commentBonus;

        $cappedKarma = min(self::MAX_POST_KARMA, $totalKarma);
        $ageDecayFactor = $this->getAgeBasedDecay($createdAt);

        return (int) round($cappedKarma * $ageDecayFactor);
    }

    private function calculateCommentKarma(int $upvotes, int $downvotes, int $repliesCount, Carbon $createdAt): int
    {
        $upvoteKarma = $this->applyDiminishingReturns($upvotes, self::BASE_COMMENT_KARMA);
        $downvoteKarma = $downvotes * self::COMMENT_DOWNVOTE_PENALTY;
        $baseKarma = $upvoteKarma - $downvoteKarma;

        $timeMultiplier = $this->getTimeBasedMultiplier($createdAt);
        $timedKarma = round($baseKarma * $timeMultiplier);

        $replyBonus = $this->calculateReplyBonus($repliesCount);
        $totalKarma = $timedKarma + $replyBonus;

        $ageDecayFactor = $this->getAgeBasedDecay($createdAt);

        return max(0, (int) round($totalKarma * $ageDecayFactor));
    }

    private function calculateActivityBonus(int $recentPosts, int $recentComments, int $recentVotes): int
    {
        $score = ($recentPosts * 3) + ($recentComments * 1) + ($recentVotes * 0.1);

        return (int) min(self::MAX_ACTIVITY_BONUS, $score);
    }

    private function calculateRelationshipKarma(int $upvotes, int $downvotes, Carbon $createdAt): int
    {
        $netVotes = $upvotes - $downvotes;

        if ($netVotes < self::MIN_VOTES_FOR_KARMA) {
            return 0;
        }

        $baseKarma = self::BASE_RELATIONSHIP_KARMA;
        $voteBonus = $netVotes * self::RELATIONSHIP_VOTE_MULTIPLIER;

        $totalKarma = $baseKarma + $voteBonus;
        $cappedKarma = min(self::MAX_RELATIONSHIP_KARMA, $totalKarma);

        $ageDecayFactor = $this->getAgeBasedDecay($createdAt);

        return (int) round($cappedKarma * $ageDecayFactor);
    }

    private function applyDiminishingReturns(int $voteCount, int $baseKarma): int
    {
        if ($voteCount <= self::DECAY_THRESHOLD_VOTES) {
            return $voteCount * $baseKarma;
        }

        $decayFactor = log($voteCount + 1) / log(self::DECAY_LOG_BASE);

        return (int) round($baseKarma * self::DECAY_THRESHOLD_VOTES * $decayFactor);
    }

    private function getTimeBasedMultiplier(Carbon $contentCreated): float
    {
        return 1.0;
    }

    private function getAgeBasedDecay(Carbon $created): float
    {
        $monthsOld = abs(now()->diffInMonths($created, false));

        return match (true) {
            $monthsOld < 1 => 1.0,    // < 1 month: 100%
            $monthsOld < 3 => 0.95,   // 1-3 months: 95%
            $monthsOld < 6 => 0.90,   // 3-6 months: 90%
            $monthsOld < 12 => 0.80,  // 6-12 months: 80%
            $monthsOld < 24 => 0.70,  // 1-2 years: 70%
            default => 0.5,           // > 2 years: 50%
        };
    }

    private function calculateCommentBonus(int $commentsCount): int
    {
        return min(self::MAX_POST_COMMENT_BONUS, $commentsCount * 1);
    }

    private function calculateReplyBonus(int $repliesCount): int
    {
        return min(self::MAX_COMMENT_REPLY_BONUS, $repliesCount * 1);
    }
}
