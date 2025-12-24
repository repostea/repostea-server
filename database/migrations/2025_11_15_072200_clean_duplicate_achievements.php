<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate user achievements from old duplicates to new equivalents
        // Then delete the old duplicate achievements

        $migrationMap = [
            // Old slug => New slug
            'posts_10' => 'posts-10',
            'posts_50' => 'posts-50',
            'posts_100' => 'posts-100',
            'comments_10' => 'comments-10',
            'comments_50' => 'comments-50',
            'comments_100' => 'comments-100',
            'votes_10' => 'votes-10',
            'votes_50' => 'votes-50',
            'votes_100' => 'votes-100',
            'karma_100' => 'karma-100',
            'karma_500' => 'karma-500',
            'karma_1000' => 'karma-1000',
            'karma_5000' => 'karma-5000',
            'karma_10000' => 'karma-10000',
            'karma_50000' => 'karma-50000',
            'streak_180' => 'streak-180',
            'content_creator' => 'content-creator',
            'active_voter' => 'active-voter',
            'community_pillar' => 'community-pillar',
            'karma_master' => 'karma-master',
        ];

        foreach ($migrationMap as $oldSlug => $newSlug) {
            // Get old and new achievement IDs
            $oldAchievement = DB::table('achievements')->where('slug', $oldSlug)->first();
            $newAchievement = DB::table('achievements')->where('slug', $newSlug)->first();

            if ($oldAchievement && $newAchievement) {
                // Migrate user achievements from old to new
                // Only migrate if user doesn't already have the new achievement
                DB::statement('
                    UPDATE achievement_user
                    SET achievement_id = ?
                    WHERE achievement_id = ?
                    AND user_id NOT IN (
                        SELECT user_id FROM (
                            SELECT user_id FROM achievement_user WHERE achievement_id = ?
                        ) as temp
                    )
                ', [$newAchievement->id, $oldAchievement->id, $newAchievement->id]);

                // Delete remaining old achievement relationships (duplicates where user already has new one)
                DB::table('achievement_user')
                    ->where('achievement_id', $oldAchievement->id)
                    ->delete();

                // Delete the old achievement
                DB::table('achievements')->where('id', $oldAchievement->id)->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse this migration as it deletes data
        // In production, you would need to restore from backup if needed
    }
};
