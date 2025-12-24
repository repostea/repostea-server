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
        $achievements = [
            // First achievements
            ['name' => 'achievements.first_post_title', 'slug' => 'first_post', 'description' => 'achievements.first_post_description', 'icon' => 'pen', 'type' => 'posts', 'requirements' => json_encode(['count' => 1, 'action' => 'post']), 'karma_bonus' => 10],
            ['name' => 'achievements.first_comment_title', 'slug' => 'first_comment', 'description' => 'achievements.first_comment_description', 'icon' => 'comment', 'type' => 'comments', 'requirements' => json_encode(['count' => 1, 'action' => 'comment']), 'karma_bonus' => 10],
            ['name' => 'achievements.first_vote_title', 'slug' => 'first_vote', 'description' => 'achievements.first_vote_description', 'icon' => 'thumbs-up', 'type' => 'vote', 'requirements' => json_encode(['count' => 1, 'action' => 'vote']), 'karma_bonus' => 5],

            // Posts achievements
            ['name' => 'achievements.posts_10_title', 'slug' => 'posts_10', 'description' => 'achievements.posts_10_description', 'icon' => 'edit', 'type' => 'posts', 'requirements' => json_encode(['count' => 10, 'action' => 'post']), 'karma_bonus' => 50],
            ['name' => 'achievements.posts_50_title', 'slug' => 'posts_50', 'description' => 'achievements.posts_50_description', 'icon' => 'file-alt', 'type' => 'posts', 'requirements' => json_encode(['count' => 50, 'action' => 'post']), 'karma_bonus' => 200],
            ['name' => 'achievements.posts_100_title', 'slug' => 'posts_100', 'description' => 'achievements.posts_100_description', 'icon' => 'newspaper', 'type' => 'posts', 'requirements' => json_encode(['count' => 100, 'action' => 'post']), 'karma_bonus' => 500],

            // Comments achievements
            ['name' => 'achievements.comments_10_title', 'slug' => 'comments_10', 'description' => 'achievements.comments_10_description', 'icon' => 'comment', 'type' => 'comments', 'requirements' => json_encode(['count' => 10, 'action' => 'comment']), 'karma_bonus' => 25],
            ['name' => 'achievements.comments_50_title', 'slug' => 'comments_50', 'description' => 'achievements.comments_50_description', 'icon' => 'comment', 'type' => 'comments', 'requirements' => json_encode(['count' => 50, 'action' => 'comment']), 'karma_bonus' => 100],
            ['name' => 'achievements.comments_100_title', 'slug' => 'comments_100', 'description' => 'achievements.comments_100_description', 'icon' => 'comments', 'type' => 'comments', 'requirements' => json_encode(['count' => 100, 'action' => 'comment']), 'karma_bonus' => 250],

            // Votes achievements
            ['name' => 'achievements.votes_10_title', 'slug' => 'votes_10', 'description' => 'achievements.votes_10_description', 'icon' => 'thumbs-up', 'type' => 'vote', 'requirements' => json_encode(['count' => 10, 'action' => 'vote']), 'karma_bonus' => 10],
            ['name' => 'achievements.votes_50_title', 'slug' => 'votes_50', 'description' => 'achievements.votes_50_description', 'icon' => 'thumbs-up', 'type' => 'vote', 'requirements' => json_encode(['count' => 50, 'action' => 'vote']), 'karma_bonus' => 50],
            ['name' => 'achievements.votes_100_title', 'slug' => 'votes_100', 'description' => 'achievements.votes_100_description', 'icon' => 'vote-yea', 'type' => 'vote', 'requirements' => json_encode(['count' => 100, 'action' => 'vote']), 'karma_bonus' => 100],

            // Streak achievements
            ['name' => 'achievements.streak_7_title', 'slug' => 'streak_7', 'description' => 'achievements.streak_7_description', 'icon' => 'fire', 'type' => 'streak', 'requirements' => json_encode(['streak' => 7]), 'karma_bonus' => 50],
            ['name' => 'achievements.streak_30_title', 'slug' => 'streak_30', 'description' => 'achievements.streak_30_description', 'icon' => 'fire', 'type' => 'streak', 'requirements' => json_encode(['streak' => 30]), 'karma_bonus' => 200],
            ['name' => 'achievements.streak_90_title', 'slug' => 'streak_90', 'description' => 'achievements.streak_90_description', 'icon' => 'fire', 'type' => 'streak', 'requirements' => json_encode(['streak' => 90]), 'karma_bonus' => 500],
            ['name' => 'achievements.streak_180_title', 'slug' => 'streak_180', 'description' => 'achievements.streak_180_description', 'icon' => 'fire', 'type' => 'streak', 'requirements' => json_encode(['streak' => 180]), 'karma_bonus' => 1000],
            ['name' => 'achievements.streak_365_title', 'slug' => 'streak_365', 'description' => 'achievements.streak_365_description', 'icon' => 'fire', 'type' => 'streak', 'requirements' => json_encode(['streak' => 365]), 'karma_bonus' => 2500],

            // Karma achievements
            ['name' => 'achievements.karma_100_title', 'slug' => 'karma_100', 'description' => 'achievements.karma_100_description', 'icon' => 'star', 'type' => 'karma', 'requirements' => json_encode(['karma' => 100]), 'karma_bonus' => 50],
            ['name' => 'achievements.karma_500_title', 'slug' => 'karma_500', 'description' => 'achievements.karma_500_description', 'icon' => 'star', 'type' => 'karma', 'requirements' => json_encode(['karma' => 500]), 'karma_bonus' => 100],
            ['name' => 'achievements.karma_1000_title', 'slug' => 'karma_1000', 'description' => 'achievements.karma_1000_description', 'icon' => 'star', 'type' => 'karma', 'requirements' => json_encode(['karma' => 1000]), 'karma_bonus' => 200],
            ['name' => 'achievements.karma_5000_title', 'slug' => 'karma_5000', 'description' => 'achievements.karma_5000_description', 'icon' => 'trophy', 'type' => 'karma', 'requirements' => json_encode(['karma' => 5000]), 'karma_bonus' => 500],
            ['name' => 'achievements.karma_10000_title', 'slug' => 'karma_10000', 'description' => 'achievements.karma_10000_description', 'icon' => 'trophy', 'type' => 'karma', 'requirements' => json_encode(['karma' => 10000]), 'karma_bonus' => 1000],
            ['name' => 'achievements.karma_50000_title', 'slug' => 'karma_50000', 'description' => 'achievements.karma_50000_description', 'icon' => 'crown', 'type' => 'karma', 'requirements' => json_encode(['karma' => 50000]), 'karma_bonus' => 5000],

            // Special achievements
            ['name' => 'achievements.conversationalist_title', 'slug' => 'conversationalist', 'description' => 'achievements.conversationalist_description', 'icon' => 'comments', 'type' => 'special', 'requirements' => json_encode(['special' => 'more_comments_than_posts', 'min_comments' => 10]), 'karma_bonus' => 250],
            ['name' => 'achievements.content_creator_title', 'slug' => 'content_creator', 'description' => 'achievements.content_creator_description', 'icon' => 'pen-fancy', 'type' => 'special', 'requirements' => json_encode(['special' => 'more_posts_than_comments', 'min_posts' => 10]), 'karma_bonus' => 250],
            ['name' => 'achievements.active_voter_title', 'slug' => 'active_voter', 'description' => 'achievements.active_voter_description', 'icon' => 'check-circle', 'type' => 'special', 'requirements' => json_encode(['special' => 'more_votes_than_posts', 'min_votes' => 50]), 'karma_bonus' => 100],
            ['name' => 'achievements.community_pillar_title', 'slug' => 'community_pillar', 'description' => 'achievements.community_pillar_description', 'icon' => 'users', 'type' => 'special', 'requirements' => json_encode(['special' => 'balanced_activity', 'min_posts' => 100, 'min_comments' => 500, 'min_votes' => 1000]), 'karma_bonus' => 2000],
            ['name' => 'achievements.karma_master_title', 'slug' => 'karma_master', 'description' => 'achievements.karma_master_description', 'icon' => 'gem', 'type' => 'special', 'requirements' => json_encode(['special' => 'quality_over_quantity', 'min_karma' => 10000, 'max_posts' => 100]), 'karma_bonus' => 1500],
        ];

        // The cleanup migration already deleted all old achievements
        // Therefore, we can insert directly without checking
        foreach ($achievements as $achievement) {
            $achievement['created_at'] = now();
            $achievement['updated_at'] = now();
            DB::table('achievements')->insert($achievement);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $slugs = [
            'first_post', 'first_comment', 'first_vote',
            'posts_10', 'posts_50', 'posts_100',
            'comments_10', 'comments_50', 'comments_100',
            'votes_10', 'votes_50', 'votes_100',
            'streak_7', 'streak_30', 'streak_90', 'streak_180', 'streak_365',
            'karma_100', 'karma_500', 'karma_1000', 'karma_5000', 'karma_10000', 'karma_50000',
            'conversationalist', 'content_creator', 'active_voter', 'community_pillar', 'karma_master',
        ];

        DB::table('achievements')->whereIn('slug', $slugs)->delete();
    }
};
