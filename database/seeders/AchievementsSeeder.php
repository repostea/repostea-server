<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class AchievementsSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            // Basic achievements (use _title and _description pattern)
            [
                'name' => 'achievements.welcome_title',
                'description' => 'achievements.welcome_description',
                'icon' => 'fas fa-hand',
                'type' => 'special',
                'requirements' => ['action' => 'register'],
                'karma_bonus' => 10,
            ],
            [
                'name' => 'achievements.first_comment_title',
                'description' => 'achievements.first_comment_description',
                'icon' => 'fas fa-comment',
                'type' => 'comments',
                'requirements' => ['action' => 'comment', 'count' => 1],
                'karma_bonus' => 5,
            ],
            [
                'name' => 'achievements.first_vote_title',
                'description' => 'achievements.first_vote_description',
                'icon' => 'fas fa-thumbs-up',
                'type' => 'karma',
                'requirements' => ['action' => 'vote', 'count' => 1],
                'karma_bonus' => 3,
            ],
            [
                'name' => 'achievements.first_post_title',
                'description' => 'achievements.first_post_description',
                'icon' => 'fas fa-pencil-alt',
                'type' => 'action',
                'requirements' => ['action' => 'post', 'count' => 1],
                'karma_bonus' => 10,
            ],
            [
                'name' => 'achievements.collaborator_bronze_title',
                'description' => 'achievements.collaborator_bronze_description',
                'icon' => 'fas fa-code-branch',
                'type' => 'special',
                'requirements' => ['special' => 'collaborator_bronze'],
                'karma_bonus' => 50,
            ],
            [
                'name' => 'achievements.collaborator_silver_title',
                'description' => 'achievements.collaborator_silver_description',
                'icon' => 'fas fa-code-branch',
                'type' => 'special',
                'requirements' => ['special' => 'collaborator_silver'],
                'karma_bonus' => 100,
            ],
            [
                'name' => 'achievements.collaborator_gold_title',
                'description' => 'achievements.collaborator_gold_description',
                'icon' => 'fas fa-code-branch',
                'type' => 'special',
                'requirements' => ['special' => 'collaborator_gold'],
                'karma_bonus' => 200,
            ],
            [
                'name' => 'achievements.early_adopter_title',
                'description' => 'achievements.early_adopter_description',
                'icon' => 'fas fa-rocket',
                'type' => 'special',
                'requirements' => ['special' => 'early_adopter'],
                'karma_bonus' => 50,
            ],

            // Streak achievements
            [
                'name' => 'achievements.streak_7_title',
                'description' => 'achievements.streak_7_description',
                'icon' => 'fas fa-calendar-week',
                'type' => 'streak',
                'requirements' => ['streak' => 7],
                'karma_bonus' => 20,
            ],
            [
                'name' => 'achievements.streak_30_title',
                'description' => 'achievements.streak_30_description',
                'icon' => 'fas fa-calendar-alt',
                'type' => 'streak',
                'requirements' => ['streak' => 30],
                'karma_bonus' => 50,
            ],
            [
                'name' => 'achievements.streak_90_title',
                'description' => 'achievements.streak_90_description',
                'icon' => 'fas fa-calendar-check',
                'type' => 'streak',
                'requirements' => ['streak' => 90],
                'karma_bonus' => 100,
            ],
            [
                'name' => 'achievements.streak_180_title',
                'description' => 'achievements.streak_180_desc',
                'icon' => 'fas fa-fire',
                'type' => 'streak',
                'requirements' => ['streak' => 180],
                'karma_bonus' => 200,
            ],
            [
                'name' => 'achievements.streak_365_title',
                'description' => 'achievements.streak_365_description',
                'icon' => 'fas fa-award',
                'type' => 'streak',
                'requirements' => ['streak' => 365],
                'karma_bonus' => 500,
            ],

            // Posts milestones
            ['name' => 'achievements.posts_5', 'description' => 'achievements.posts_5_desc', 'icon' => 'fas fa-edit', 'type' => 'posts', 'requirements' => ['posts' => 5], 'karma_bonus' => 5],
            ['name' => 'achievements.posts_10', 'description' => 'achievements.posts_10_desc', 'icon' => 'fas fa-edit', 'type' => 'posts', 'requirements' => ['posts' => 10], 'karma_bonus' => 10],
            ['name' => 'achievements.posts_25', 'description' => 'achievements.posts_25_desc', 'icon' => 'fas fa-file-alt', 'type' => 'posts', 'requirements' => ['posts' => 25], 'karma_bonus' => 25],
            ['name' => 'achievements.posts_50', 'description' => 'achievements.posts_50_desc', 'icon' => 'fas fa-file-alt', 'type' => 'posts', 'requirements' => ['posts' => 50], 'karma_bonus' => 50],
            ['name' => 'achievements.posts_100', 'description' => 'achievements.posts_100_desc', 'icon' => 'fas fa-newspaper', 'type' => 'posts', 'requirements' => ['posts' => 100], 'karma_bonus' => 100],
            ['name' => 'achievements.posts_250', 'description' => 'achievements.posts_250_desc', 'icon' => 'fas fa-newspaper', 'type' => 'posts', 'requirements' => ['posts' => 250], 'karma_bonus' => 250],
            ['name' => 'achievements.posts_500', 'description' => 'achievements.posts_500_desc', 'icon' => 'fas fa-book', 'type' => 'posts', 'requirements' => ['posts' => 500], 'karma_bonus' => 500],

            // Comments milestones
            ['name' => 'achievements.comments_10', 'description' => 'achievements.comments_10_desc', 'icon' => 'fas fa-comment', 'type' => 'comments', 'requirements' => ['comments' => 10], 'karma_bonus' => 10],
            ['name' => 'achievements.comments_50', 'description' => 'achievements.comments_50_desc', 'icon' => 'fas fa-comment', 'type' => 'comments', 'requirements' => ['comments' => 50], 'karma_bonus' => 50],
            ['name' => 'achievements.comments_100', 'description' => 'achievements.comments_100_desc', 'icon' => 'fas fa-comments', 'type' => 'comments', 'requirements' => ['comments' => 100], 'karma_bonus' => 100],
            ['name' => 'achievements.comments_500', 'description' => 'achievements.comments_500_desc', 'icon' => 'fas fa-comments', 'type' => 'comments', 'requirements' => ['comments' => 500], 'karma_bonus' => 500],
            ['name' => 'achievements.comments_1000', 'description' => 'achievements.comments_1000_desc', 'icon' => 'fas fa-comment-dots', 'type' => 'comments', 'requirements' => ['comments' => 1000], 'karma_bonus' => 1000],

            // Votes milestones
            ['name' => 'achievements.votes_10', 'description' => 'achievements.votes_10_desc', 'icon' => 'fas fa-thumbs-up', 'type' => 'vote', 'requirements' => ['votes' => 10], 'karma_bonus' => 5],
            ['name' => 'achievements.votes_50', 'description' => 'achievements.votes_50_desc', 'icon' => 'fas fa-thumbs-up', 'type' => 'vote', 'requirements' => ['votes' => 50], 'karma_bonus' => 10],
            ['name' => 'achievements.votes_100', 'description' => 'achievements.votes_100_desc', 'icon' => 'fas fa-check-to-slot', 'type' => 'vote', 'requirements' => ['votes' => 100], 'karma_bonus' => 20],
            ['name' => 'achievements.votes_500', 'description' => 'achievements.votes_500_desc', 'icon' => 'fas fa-check-to-slot', 'type' => 'vote', 'requirements' => ['votes' => 500], 'karma_bonus' => 50],
            ['name' => 'achievements.votes_1000', 'description' => 'achievements.votes_1000_desc', 'icon' => 'fas fa-poll', 'type' => 'vote', 'requirements' => ['votes' => 1000], 'karma_bonus' => 100],
            ['name' => 'achievements.votes_5000', 'description' => 'achievements.votes_5000_desc', 'icon' => 'fas fa-poll-h', 'type' => 'vote', 'requirements' => ['votes' => 5000], 'karma_bonus' => 500],

            // Karma milestones
            ['name' => 'achievements.karma_100_title', 'description' => 'achievements.karma_100_description', 'icon' => 'fas fa-star', 'type' => 'karma', 'requirements' => ['karma' => 100], 'karma_bonus' => 0],
            ['name' => 'achievements.karma_500_title', 'description' => 'achievements.karma_500_desc', 'icon' => 'fas fa-star', 'type' => 'karma', 'requirements' => ['karma' => 500], 'karma_bonus' => 0],
            ['name' => 'achievements.karma_1000_title', 'description' => 'achievements.karma_1000_description', 'icon' => 'fas fa-star-half-alt', 'type' => 'karma', 'requirements' => ['karma' => 1000], 'karma_bonus' => 0],
            ['name' => 'achievements.karma_2000_title', 'description' => 'achievements.karma_2000_desc', 'icon' => 'fas fa-star', 'type' => 'karma', 'requirements' => ['karma' => 2000], 'karma_bonus' => 0],
            ['name' => 'achievements.karma_5000_title', 'description' => 'achievements.karma_5000_desc', 'icon' => 'fas fa-trophy', 'type' => 'karma', 'requirements' => ['karma' => 5000], 'karma_bonus' => 0],
            ['name' => 'achievements.karma_10000_title', 'description' => 'achievements.karma_10000_desc', 'icon' => 'fas fa-trophy', 'type' => 'karma', 'requirements' => ['karma' => 10000], 'karma_bonus' => 0],
            ['name' => 'achievements.karma_25000_title', 'description' => 'achievements.karma_25000_desc', 'icon' => 'fas fa-crown', 'type' => 'karma', 'requirements' => ['karma' => 25000], 'karma_bonus' => 0],
            ['name' => 'achievements.karma_50000_title', 'description' => 'achievements.karma_50000_desc', 'icon' => 'fas fa-crown', 'type' => 'karma', 'requirements' => ['karma' => 50000], 'karma_bonus' => 0],

            // Special achievements
            ['name' => 'achievements.conversationalist', 'description' => 'achievements.conversationalist_desc', 'icon' => 'fas fa-comments', 'type' => 'special', 'requirements' => ['special' => 'conversationalist'], 'karma_bonus' => 50],
            ['name' => 'achievements.content_creator', 'description' => 'achievements.content_creator_desc', 'icon' => 'fas fa-pen-fancy', 'type' => 'special', 'requirements' => ['special' => 'content_creator'], 'karma_bonus' => 50],
            ['name' => 'achievements.active_voter', 'description' => 'achievements.active_voter_desc', 'icon' => 'fas fa-check-circle', 'type' => 'special', 'requirements' => ['special' => 'active_voter'], 'karma_bonus' => 50],
            ['name' => 'achievements.community_pillar', 'description' => 'achievements.community_pillar_desc', 'icon' => 'fas fa-users', 'type' => 'special', 'requirements' => ['special' => 'community_pillar'], 'karma_bonus' => 200],
            ['name' => 'achievements.karma_master', 'description' => 'achievements.karma_master_desc', 'icon' => 'fas fa-gem', 'type' => 'special', 'requirements' => ['special' => 'karma_master'], 'karma_bonus' => 500],

            // Sub (community) achievements
            ['name' => 'achievements.first_sub_title', 'description' => 'achievements.first_sub_description', 'icon' => 'fas fa-sitemap', 'type' => 'subs', 'requirements' => ['action' => 'create_sub', 'count' => 1], 'karma_bonus' => 20],
            ['name' => 'achievements.sub_members_10', 'description' => 'achievements.sub_members_10_desc', 'icon' => 'fas fa-users', 'type' => 'sub_members', 'requirements' => ['sub_members' => 10], 'karma_bonus' => 20],
            ['name' => 'achievements.sub_members_50', 'description' => 'achievements.sub_members_50_desc', 'icon' => 'fas fa-users', 'type' => 'sub_members', 'requirements' => ['sub_members' => 50], 'karma_bonus' => 50],
            ['name' => 'achievements.sub_members_100', 'description' => 'achievements.sub_members_100_desc', 'icon' => 'fas fa-user-friends', 'type' => 'sub_members', 'requirements' => ['sub_members' => 100], 'karma_bonus' => 100],
            ['name' => 'achievements.sub_members_500', 'description' => 'achievements.sub_members_500_desc', 'icon' => 'fas fa-user-friends', 'type' => 'sub_members', 'requirements' => ['sub_members' => 500], 'karma_bonus' => 200],
            ['name' => 'achievements.sub_members_1000', 'description' => 'achievements.sub_members_1000_desc', 'icon' => 'fas fa-globe', 'type' => 'sub_members', 'requirements' => ['sub_members' => 1000], 'karma_bonus' => 500],
            ['name' => 'achievements.sub_posts_10', 'description' => 'achievements.sub_posts_10_desc', 'icon' => 'fas fa-newspaper', 'type' => 'sub_posts', 'requirements' => ['sub_posts' => 10], 'karma_bonus' => 15],
            ['name' => 'achievements.sub_posts_50', 'description' => 'achievements.sub_posts_50_desc', 'icon' => 'fas fa-newspaper', 'type' => 'sub_posts', 'requirements' => ['sub_posts' => 50], 'karma_bonus' => 40],
            ['name' => 'achievements.sub_posts_100', 'description' => 'achievements.sub_posts_100_desc', 'icon' => 'fas fa-book-open', 'type' => 'sub_posts', 'requirements' => ['sub_posts' => 100], 'karma_bonus' => 80],
            ['name' => 'achievements.sub_posts_500', 'description' => 'achievements.sub_posts_500_desc', 'icon' => 'fas fa-book-open', 'type' => 'sub_posts', 'requirements' => ['sub_posts' => 500], 'karma_bonus' => 200],
        ];

        foreach ($achievements as $achievementData) {
            $slugMap = [
                // Basic achievements
                'achievements.welcome_title' => 'welcome',
                'achievements.first_comment_title' => 'first_comment',
                'achievements.first_vote_title' => 'first_vote',
                'achievements.first_post_title' => 'first_post',
                'achievements.collaborator_bronze_title' => 'collaborator_bronze',
                'achievements.collaborator_silver_title' => 'collaborator_silver',
                'achievements.collaborator_gold_title' => 'collaborator_gold',
                'achievements.early_adopter_title' => 'early_adopter',

                // Streak achievements
                'achievements.streak_7_title' => 'streak_7',
                'achievements.streak_30_title' => 'streak_30',
                'achievements.streak_90_title' => 'streak_90',
                'achievements.streak_180_title' => 'streak-180',
                'achievements.streak_365_title' => 'streak_365',

                // Posts milestones (use hyphen for auto-generated)
                'achievements.posts_5' => 'posts-5',
                'achievements.posts_10' => 'posts-10',
                'achievements.posts_25' => 'posts-25',
                'achievements.posts_50' => 'posts-50',
                'achievements.posts_100' => 'posts-100',
                'achievements.posts_250' => 'posts-250',
                'achievements.posts_500' => 'posts-500',

                // Comments milestones
                'achievements.comments_10' => 'comments-10',
                'achievements.comments_50' => 'comments-50',
                'achievements.comments_100' => 'comments-100',
                'achievements.comments_500' => 'comments-500',
                'achievements.comments_1000' => 'comments-1000',

                // Votes milestones
                'achievements.votes_10' => 'votes-10',
                'achievements.votes_50' => 'votes-50',
                'achievements.votes_100' => 'votes-100',
                'achievements.votes_500' => 'votes-500',
                'achievements.votes_1000' => 'votes-1000',
                'achievements.votes_5000' => 'votes-5000',

                // Karma milestones
                'achievements.karma_100_title' => 'karma_100',
                'achievements.karma_500_title' => 'karma-500',
                'achievements.karma_1000_title' => 'karma_1000',
                'achievements.karma_2000_title' => 'karma-2000',
                'achievements.karma_5000_title' => 'karma-5000',
                'achievements.karma_10000_title' => 'karma-10000',
                'achievements.karma_25000_title' => 'karma-25000',
                'achievements.karma_50000_title' => 'karma-50000',

                // Special achievements
                'achievements.conversationalist' => 'conversationalist',
                'achievements.content_creator' => 'content-creator',
                'achievements.active_voter' => 'active-voter',
                'achievements.community_pillar' => 'community-pillar',
                'achievements.karma_master' => 'karma-master',

                // Sub achievements
                'achievements.first_sub_title' => 'first_sub',
                'achievements.sub_members_10' => 'sub-members-10',
                'achievements.sub_members_50' => 'sub-members-50',
                'achievements.sub_members_100' => 'sub-members-100',
                'achievements.sub_members_500' => 'sub-members-500',
                'achievements.sub_members_1000' => 'sub-members-1000',
                'achievements.sub_posts_10' => 'sub-posts-10',
                'achievements.sub_posts_50' => 'sub-posts-50',
                'achievements.sub_posts_100' => 'sub-posts-100',
                'achievements.sub_posts_500' => 'sub-posts-500',
            ];

            $slug = $slugMap[$achievementData['name']] ?? Str::slug($achievementData['name']);

            Achievement::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $achievementData['name'],
                    'description' => $achievementData['description'],
                    'icon' => $achievementData['icon'],
                    'type' => $achievementData['type'],
                    'requirements' => $achievementData['requirements'],
                    'karma_bonus' => $achievementData['karma_bonus'],
                ],
            );
        }
    }
}
