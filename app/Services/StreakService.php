<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserStreak;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Tracks user activity streaks and triggers streak-based achievements.
 */
final class StreakService
{
    public function recordActivity(User $user): UserStreak
    {
        try {
            $streak = UserStreak::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'current_streak' => 0,
                    'longest_streak' => 0,
                    'last_activity_date' => null,
                ],
            );

            $today = Carbon::today();

            if ($streak->last_activity_date === null) {
                $streak->current_streak = 1;
                $streak->longest_streak = 1;
                $streak->last_activity_date = $today;
            } else {
                $lastDate = $streak->last_activity_date;
                $daysGap = (int) abs($today->diffInDays($lastDate));

                if ($daysGap === 0) {
                    // Same day, no change needed
                    return $streak;
                }

                if ($daysGap === 1 || ($today->isMonday() && $lastDate->isFriday())) {
                    $streak->current_streak += 1;
                    $streak->last_activity_date = $today;

                    if ($streak->current_streak > $streak->longest_streak) {
                        $streak->longest_streak = $streak->current_streak;
                    }

                    $this->checkStreakAchievements($user, $streak);
                } else {
                    $streak->current_streak = 1;
                    $streak->last_activity_date = $today;
                }
            }

            $streak->save();

            return $streak;

        } catch (Exception $e) {
            Log::error('Error updating user streak', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function checkStreakAchievements(User $user, UserStreak $streak): void
    {
        $achievementService = app(AchievementService::class);

        $streakMilestones = [7, 30, 90, 180, 365];

        foreach ($streakMilestones as $milestone) {
            if ($streak->current_streak >= $milestone) {
                $achievementSlug = "streak-{$milestone}";
                $achievementService->unlockIfExists($user, $achievementSlug);
            }
        }
    }
}
