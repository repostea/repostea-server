<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class NotificationsSeeder extends Seeder
{
    public function run(): void
    {
        // Get the first user (or create one if none exists)
        $user = User::first();

        if ($user === null) {
            $this->command->error('No users found. Please create a user first.');

            return;
        }

        $this->command->info("Creating notifications for user: {$user->username}");

        $notifications = [
            // Post Comments (3 notifications, 2 unread)
            [
                'type' => 'App\\Notifications\\PostCommented',
                'notifiable_type' => get_class($user),
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'type' => 'post_comment',
                    'title' => 'New comment on your post',
                    'body' => 'Someone commented on your post "Example Post Title"',
                    'user_id' => 2,
                    'username' => 'testuser',
                    'user_avatar' => '/default-avatar.png',
                    'post_id' => 1,
                    'post_title' => 'Example Post Title',
                    'post_comments_count' => 5,
                    'comment_id' => 1,
                    'action_url' => '/post/example-post-title',
                ]),
                'read_at' => null,
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ],
            [
                'type' => 'App\\Notifications\\PostCommented',
                'notifiable_type' => get_class($user),
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'type' => 'post_comment',
                    'title' => 'New comment on your post',
                    'body' => 'Another user commented on your post',
                    'user_id' => 3,
                    'username' => 'anotheruser',
                    'user_avatar' => '/default-avatar.png',
                    'post_id' => 2,
                    'post_title' => 'Another Post',
                    'post_comments_count' => 3,
                    'comment_id' => 2,
                    'action_url' => '/post/another-post',
                ]),
                'read_at' => null,
                'created_at' => now()->subHours(5),
                'updated_at' => now()->subHours(5),
            ],
            [
                'type' => 'App\\Notifications\\PostCommented',
                'notifiable_type' => get_class($user),
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'type' => 'post_comment',
                    'title' => 'New comment on your post',
                    'body' => 'Old comment (already read)',
                    'user_id' => 4,
                    'username' => 'olduser',
                    'user_avatar' => '/default-avatar.png',
                    'post_id' => 3,
                    'post_title' => 'Old Post',
                    'post_comments_count' => 1,
                    'comment_id' => 3,
                    'action_url' => '/post/old-post',
                ]),
                'read_at' => now()->subDay(),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDay(),
            ],

            // Comment Replies (2 notifications, 1 unread)
            [
                'type' => 'App\\Notifications\\CommentReplied',
                'notifiable_type' => get_class($user),
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'type' => 'comment_reply',
                    'title' => 'Reply to your comment',
                    'body' => 'Someone replied to your comment',
                    'user_id' => 2,
                    'username' => 'replier',
                    'user_avatar' => '/default-avatar.png',
                    'post_id' => 1,
                    'post_title' => 'Discussion Post',
                    'comment_id' => 10,
                    'parent_comment_id' => 9,
                    'action_url' => '/post/discussion-post#comment-10',
                ]),
                'read_at' => null,
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(3),
            ],
            [
                'type' => 'App\\Notifications\\CommentReplied',
                'notifiable_type' => get_class($user),
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'type' => 'comment_reply',
                    'title' => 'Reply to your comment',
                    'body' => 'Old reply (already read)',
                    'user_id' => 3,
                    'username' => 'oldreplier',
                    'user_avatar' => '/default-avatar.png',
                    'post_id' => 2,
                    'post_title' => 'Old Discussion',
                    'comment_id' => 11,
                    'parent_comment_id' => 10,
                    'action_url' => '/post/old-discussion#comment-11',
                ]),
                'read_at' => now()->subDay(),
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDay(),
            ],

            // Mentions (1 notification, unread)
            [
                'type' => 'App\\Notifications\\UserMentioned',
                'notifiable_type' => get_class($user),
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'type' => 'mention',
                    'title' => 'You were mentioned',
                    'body' => '@' . $user->username . ' check this out!',
                    'user_id' => 2,
                    'username' => 'mentioner',
                    'user_avatar' => '/default-avatar.png',
                    'post_id' => 5,
                    'post_title' => 'Interesting Discussion',
                    'comment_id' => 20,
                    'action_url' => '/post/interesting-discussion#comment-20',
                ]),
                'read_at' => null,
                'created_at' => now()->subHour(),
                'updated_at' => now()->subHour(),
            ],

            // Achievements (3 notifications, all unread)
            [
                'type' => 'App\\Notifications\\AchievementUnlocked',
                'notifiable_type' => get_class($user),
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'type' => 'achievement',
                    'title' => 'Achievement Unlocked!',
                    'body' => 'You unlocked "First Post" achievement',
                    'achievement_name' => 'First Post',
                    'achievement_description' => 'Create your first post',
                    'achievement_icon' => '🎉',
                    'action_url' => '/profile/achievements',
                ]),
                'read_at' => null,
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(6),
            ],
            [
                'type' => 'App\\Notifications\\AchievementUnlocked',
                'notifiable_type' => get_class($user),
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'type' => 'achievement',
                    'title' => 'Achievement Unlocked!',
                    'body' => 'You unlocked "10 Comments" achievement',
                    'achievement_name' => '10 Comments',
                    'achievement_description' => 'Post 10 comments',
                    'achievement_icon' => '💬',
                    'action_url' => '/profile/achievements',
                ]),
                'read_at' => null,
                'created_at' => now()->subHours(12),
                'updated_at' => now()->subHours(12),
            ],
            [
                'type' => 'App\\Notifications\\KarmaLevelUp',
                'notifiable_type' => get_class($user),
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'type' => 'karma_level_up',
                    'title' => 'Karma Level Up!',
                    'body' => 'You reached karma level 2',
                    'old_level' => 1,
                    'new_level' => 2,
                    'karma_points' => 100,
                    'action_url' => '/profile',
                ]),
                'read_at' => null,
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
        ];

        // Insert notifications
        foreach ($notifications as $notification) {
            $notification['id'] = \Illuminate\Support\Str::uuid()->toString();
            DB::table('notifications')->insert($notification);
        }

        $this->command->info('✅ Created ' . count($notifications) . ' test notifications');
        $this->command->info('Summary:');
        $this->command->info('  - Post Comments: 3 (2 unread)');
        $this->command->info('  - Comment Replies: 2 (1 unread)');
        $this->command->info('  - Mentions: 1 (1 unread)');
        $this->command->info('  - Achievements: 3 (3 unread)');
    }
}
