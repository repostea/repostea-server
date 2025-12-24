<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Sub;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Awards karma to subcommunity creators based on activity in their subs.
 */
final class SubcommunityKarmaService
{
    /**
     * Award karma to creator for new member in their sub.
     */
    public function awardKarmaForNewMember(Sub $sub): void
    {
        if (! $sub->created_by) {
            return;
        }

        $karma = config('subcommunity_karma.member_join', 5);
        $membersCount = $sub->members_count;

        // Milestone bonuses
        if ($membersCount === 10) {
            $karma += config('subcommunity_karma.milestone_10_members', 50);
        } elseif ($membersCount === 100) {
            $karma += config('subcommunity_karma.milestone_100_members', 500);
        } elseif ($membersCount === 1000) {
            $karma += config('subcommunity_karma.milestone_1000_members', 5000);
        }

        $this->awardKarma($sub, 'member_join', $karma, [
            'members_count' => $membersCount,
        ]);
    }

    /**
     * Award karma for new post in the sub.
     */
    public function awardKarmaForNewPost(Sub $sub): void
    {
        if (! $sub->created_by) {
            return;
        }

        $karma = config('subcommunity_karma.post_created', 2);
        $karma = $this->applyMultiplier($karma, $sub);

        $this->awardKarma($sub, 'post_created', $karma);
    }

    /**
     * Award karma when a sub post reaches the frontpage.
     */
    public function awardKarmaForFrontpage(Sub $sub): void
    {
        if (! $sub->created_by) {
            return;
        }

        $karma = config('subcommunity_karma.post_frontpage', 20);
        $karma = $this->applyMultiplier($karma, $sub);

        $this->awardKarma($sub, 'post_frontpage', $karma);
    }

    /**
     * Award karma for new comment in the sub.
     */
    public function awardKarmaForNewComment(Sub $sub): void
    {
        if (! $sub->created_by) {
            return;
        }

        $karma = config('subcommunity_karma.comment_created', 1);
        $karma = $this->applyMultiplier($karma, $sub);

        $this->awardKarma($sub, 'comment_created', $karma);
    }

    /**
     * Award karma for post upvote in the sub.
     */
    public function awardKarmaForPostUpvote(Sub $sub): void
    {
        if (! $sub->created_by) {
            return;
        }

        $karma = config('subcommunity_karma.post_upvote', 0.5);
        $karma = $this->applyMultiplier($karma, $sub);

        $this->awardKarma($sub, 'post_upvote', $karma);
    }

    /**
     * Award karma for comment upvote in the sub.
     */
    public function awardKarmaForCommentUpvote(Sub $sub): void
    {
        if (! $sub->created_by) {
            return;
        }

        $karma = config('subcommunity_karma.comment_upvote', 0.3);
        $karma = $this->applyMultiplier($karma, $sub);

        $this->awardKarma($sub, 'comment_upvote', $karma);
    }

    /**
     * Award karma for correctly resolved report.
     */
    public function awardKarmaForReportResolved(Sub $sub): void
    {
        if (! $sub->created_by) {
            return;
        }

        $karma = config('subcommunity_karma.report_resolved', 3);
        $this->awardKarma($sub, 'report_resolved', $karma);
    }

    /**
     * Apply multiplier based on community size.
     */
    private function applyMultiplier(float $karma, Sub $sub): float
    {
        $membersCount = $sub->members_count;
        $multipliers = config('subcommunity_karma.size_multipliers');

        if ($membersCount < 100) {
            return $karma * $multipliers['small'];
        }

        if ($membersCount < 1000) {
            return $karma * $multipliers['medium'];
        }

        if ($membersCount < 10000) {
            return $karma * $multipliers['large'];
        }

        return $karma * $multipliers['massive'];
    }

    /**
     * Award karma to the sub creator with daily limits.
     */
    private function awardKarma(Sub $sub, string $reason, float $karma, array $metadata = []): void
    {
        $creator = User::find($sub->created_by);

        if (! $creator) {
            return;
        }

        // Check daily limit per sub
        $cacheKey = "sub_karma_daily_{$sub->id}_" . now()->format('Y-m-d');
        $dailyKarma = (int) Cache::get($cacheKey, 0);
        $maxDaily = config('subcommunity_karma.max_karma_per_day_per_sub', 1000);

        if ($dailyKarma >= $maxDaily) {
            Log::info('Daily karma limit reached for sub', [
                'sub_id' => $sub->id,
                'creator_id' => $creator->id,
                'daily_karma' => $dailyKarma,
            ]);

            return;
        }

        // Apply per-event limit
        $maxPerEvent = config('subcommunity_karma.max_karma_per_event', 100);
        $karma = min($karma, $maxPerEvent);

        // Verify it doesn't exceed the daily limit
        $karma = min($karma, $maxDaily - $dailyKarma);

        if ($karma <= 0) {
            return;
        }

        // Update daily karma cache
        Cache::put($cacheKey, $dailyKarma + $karma, now()->endOfDay());

        // Award karma to the user
        DB::transaction(function () use ($creator, $karma, $sub, $reason, $metadata): void {
            $creator->increment('karma_points', (int) round($karma));

            // Record in karma_histories
            DB::table('karma_histories')->insert([
                'user_id' => $creator->id,
                'amount' => (int) round($karma),
                'source' => "sub_{$reason}",
                'source_id' => $sub->id,
                'description' => json_encode(array_merge($metadata, [
                    'sub_name' => $sub->name,
                    'sub_members' => $sub->members_count,
                    'reason' => $reason,
                ])),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Log::info('Karma awarded to sub creator', [
            'sub_id' => $sub->id,
            'creator_id' => $creator->id,
            'reason' => $reason,
            'karma' => $karma,
        ]);
    }

    /**
     * Calculate total karma generated by a sub.
     */
    public function calculateTotalKarmaFromSub(Sub $sub): int
    {
        if (! $sub->created_by) {
            return 0;
        }

        return (int) DB::table('karma_histories')
            ->where('user_id', $sub->created_by)
            ->where('source_id', $sub->id)
            ->where('source', 'like', 'sub_%')
            ->sum('amount');
    }
}
