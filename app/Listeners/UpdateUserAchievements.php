<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserStreak;
use App\Services\AchievementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class UpdateUserAchievements implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct(
        private AchievementService $achievementService,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(UserStreak $event): void
    {
        $user = $event->user;
        $streakDays = $event->streakDays;

        // Check streak-related achievements
        $this->checkStreakAchievements($user, $streakDays);

        // Check other achievements that may be affected by user activity
        $this->checkActivityAchievements($user);
    }

    /**
     * Check streak-related achievements.
     */
    private function checkStreakAchievements($user, $streakDays): void
    {
        // Streak-based achievements
        $streakMilestones = [7, 30, 90, 180, 365];

        foreach ($streakMilestones as $milestone) {
            if ($streakDays >= $milestone) {
                $achievementSlug = "streak-{$milestone}";
                $this->achievementService->unlockIfExists($user, $achievementSlug);
            }
        }
    }

    /**
     * Check achievements related to general activity.
     */
    private function checkActivityAchievements($user): void
    {
        // Count user activity
        $postsCount = $user->posts()->count();
        $commentsCount = $user->comments()->count();
        $votesCount = $user->votes()->count();

        // Post creation-based achievements
        $postMilestones = [1, 5, 10, 25, 50, 100];
        foreach ($postMilestones as $milestone) {
            if ($postsCount >= $milestone) {
                $achievementSlug = "posts-{$milestone}";
                $this->achievementService->unlockIfExists($user, $achievementSlug);
            }
        }

        // Comment-based achievements
        $commentMilestones = [1, 10, 50, 100, 500];
        foreach ($commentMilestones as $milestone) {
            if ($commentsCount >= $milestone) {
                $achievementSlug = "comments-{$milestone}";
                $this->achievementService->unlockIfExists($user, $achievementSlug);
            }
        }

        // Vote-based achievements
        $voteMilestones = [10, 50, 100, 500, 1000];
        foreach ($voteMilestones as $milestone) {
            if ($votesCount >= $milestone) {
                $achievementSlug = "votes-{$milestone}";
                $this->achievementService->unlockIfExists($user, $achievementSlug);
            }
        }
    }
}
