<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\AchievementUnlocked;
use App\Models\Achievement;
use App\Models\Sub;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;

final class AchievementService
{
    public function unlockIfExists(User $user, string $achievementSlug): ?Achievement
    {
        try {
            $achievement = Achievement::where('slug', $achievementSlug)->first();

            if ($achievement === null) {
                return null;
            }

            return $this->unlock($user, $achievement);

        } catch (Exception $e) {
            Log::error('Error unlocking achievement', [
                'user_id' => $user->id,
                'achievement_slug' => $achievementSlug,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function unlock(User $user, Achievement $achievement): Achievement
    {
        $pivotData = [
            'progress' => 100,
            'unlocked_at' => now(),
        ];

        if ($user->achievements()->where('achievement_id', $achievement->id)->exists()) {
            $user->achievements()->updateExistingPivot($achievement->id, $pivotData);
        } else {
            $user->achievements()->attach($achievement->id, $pivotData);

            if ($achievement->karma_bonus > 0) {
                $user->updateKarma($achievement->karma_bonus);
            }

            event(new AchievementUnlocked($user, $achievement));
        }

        return $achievement;
    }

    public function updateProgress(User $user, string $achievementSlug, int $progress): ?Achievement
    {
        try {
            $achievement = Achievement::where('slug', $achievementSlug)->first();

            if ($achievement === null) {
                return null;
            }

            $pivotRecord = $user->achievements()
                ->where('achievement_id', $achievement->id)
                ->first();

            $currentProgress = 0;
            if ($pivotRecord !== null && $pivotRecord->pivot !== null) {
                $currentProgress = $pivotRecord->pivot->progress ?? 0;
            }

            $newProgress = min(100, $currentProgress + $progress);

            if ($newProgress >= 100) {
                return $this->unlock($user, $achievement);
            }

            $pivotData = [
                'progress' => $newProgress,
                'unlocked_at' => null,
            ];

            if ($user->achievements()->where('achievement_id', $achievement->id)->exists()) {
                $user->achievements()->updateExistingPivot($achievement->id, $pivotData);
            } else {
                $user->achievements()->attach($achievement->id, $pivotData);
            }

            return $achievement;

        } catch (Exception $e) {
            Log::error('Error updating achievement progress', [
                'user_id' => $user->id,
                'achievement_slug' => $achievementSlug,
                'progress' => $progress,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check and unlock sub member count achievements.
     */
    public function checkSubMemberAchievements(Sub $sub): void
    {
        $creator = $sub->creator;
        if (! $creator) {
            return;
        }

        $memberCount = $sub->members_count;
        $thresholds = [10, 50, 100, 500, 1000];

        foreach ($thresholds as $threshold) {
            if ($memberCount >= $threshold) {
                $this->unlockIfExists($creator, "sub-members-{$threshold}");
            }
        }
    }

    /**
     * Check and unlock sub posts count achievements.
     */
    public function checkSubPostsAchievements(Sub $sub): void
    {
        $creator = $sub->creator;
        if (! $creator) {
            return;
        }

        $postsCount = $sub->posts_count;
        $thresholds = [10, 50, 100, 500];

        foreach ($thresholds as $threshold) {
            if ($postsCount >= $threshold) {
                $this->unlockIfExists($creator, "sub-posts-{$threshold}");
            }
        }
    }

    /**
     * Check all sub-related achievements.
     */
    public function checkSubAchievements(Sub $sub): void
    {
        $this->checkSubMemberAchievements($sub);
        $this->checkSubPostsAchievements($sub);
    }
}
