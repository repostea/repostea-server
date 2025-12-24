<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Achievement;
use App\Models\PostRelationship;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Checks and grants achievements based on post relationships.
 */
final class RelationshipAchievementChecker
{
    /**
     * @return array<int, Achievement>
     */
    public function checkAchievements(User $user): array
    {
        $granted = [];

        // Get all relationship achievements
        $achievements = Achievement::where('requirements->type', 'relationships')->get();

        foreach ($achievements as $achievement) {
            // Skip if user already has this achievement
            if ($user->achievements()->where('achievement_id', $achievement->id)->where('unlocked_at', '!=', null)->exists()) {
                continue;
            }

            $requirements = $achievement->requirements;
            $requiredCount = $requirements['count'] ?? 0;
            $minScore = $requirements['min_score'] ?? 0;

            // Count relationships created by user that meet the score requirement
            $qualifyingCount = PostRelationship::where('created_by', $user->id)
                ->where('score', '>=', $minScore)
                ->count();

            // Check if user qualifies for this achievement
            if ($qualifyingCount >= $requiredCount) {
                $this->grantAchievement($user, $achievement);
                $granted[] = $achievement;
            } else {
                // Update progress
                $this->updateProgress($user, $achievement, $qualifyingCount, $requiredCount);
            }
        }

        return $granted;
    }

    /**
     * Grant an achievement to a user.
     */
    private function grantAchievement(User $user, Achievement $achievement): void
    {
        DB::table('achievement_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
            ],
            [
                'progress' => 100,
                'unlocked_at' => now(),
                'updated_at' => now(),
            ],
        );

        // Award karma bonus
        if ($achievement->karma_bonus > 0) {
            $user->updateKarma($achievement->karma_bonus);
            $user->recordKarma(
                $achievement->karma_bonus,
                'achievement',
                $achievement->id,
                "Logro desbloqueado: {$achievement->name}",
            );
        }
    }

    /**
     * Update achievement progress for a user.
     */
    private function updateProgress(User $user, Achievement $achievement, int $current, int $required): void
    {
        $progress = min(100, (int) (($current / $required) * 100));

        DB::table('achievement_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
            ],
            [
                'progress' => $progress,
                'updated_at' => now(),
            ],
        );
    }

    /**
     * @return array<int, Achievement>
     */
    public function checkAfterVote(int $relationshipId): array
    {
        $relationship = PostRelationship::find($relationshipId);
        if (! $relationship) {
            return [];
        }

        $creator = User::find($relationship->created_by);
        if (! $creator) {
            return [];
        }

        return $this->checkAchievements($creator);
    }
}
