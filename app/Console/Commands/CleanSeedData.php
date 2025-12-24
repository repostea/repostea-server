<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CleanSeedData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'seed:clean {--force : Force the operation without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Clean all seed data from production (users, posts, comments) keeping only admin user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn('⚠️  WARNING: This will delete seed data from the database!');
        $this->newLine();

        // IDs to delete
        $userIdsToDelete = [
            1, // alexios_clever_170 (guest)
            3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, // factory users
            23, 24, 25, 26, 27, 28, 29, 30, // AI users
        ];

        $postIdsToDelete = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15]; // All seed posts including "asdfasd"

        $this->info('📋 Users to delete: ' . count($userIdsToDelete));
        $this->info('📋 Posts to delete: ' . count($postIdsToDelete));
        $this->newLine();

        // Show what will be kept
        $this->info('✅ Will KEEP:');
        $admin = User::find(2);
        if ($admin) {
            $this->line("   - User ID 2: {$admin->username} ({$admin->email})");
        }
        $this->newLine();

        if (! $this->option('force')) {
            if (! $this->confirm('Do you want to proceed with deletion?')) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        Log::info('====== SEED DATA CLEANUP STARTED ======', [
            'timestamp' => now()->toDateTimeString(),
            'users_to_delete' => count($userIdsToDelete),
            'posts_to_delete' => count($postIdsToDelete),
        ]);

        DB::beginTransaction();

        try {
            // Step 1: Delete comments associated with posts
            $this->info('🗑️  Deleting comments from seed posts...');
            $deletedComments = Comment::whereIn('post_id', $postIdsToDelete)->delete();
            $this->info("   ✅ Deleted {$deletedComments} comments");

            // Step 2: Delete comments created by seed users
            $this->info('🗑️  Deleting comments from seed users...');
            $deletedUserComments = Comment::whereIn('user_id', $userIdsToDelete)->delete();
            $this->info("   ✅ Deleted {$deletedUserComments} comments");

            // Step 3: Delete votes on posts to be deleted
            $this->info('🗑️  Deleting votes from seed posts...');
            $deletedVotes = DB::table('votes')
                ->where('votable_type', 'App\\Models\\Post')
                ->whereIn('votable_id', $postIdsToDelete)
                ->delete();
            $this->info("   ✅ Deleted {$deletedVotes} votes");

            // Step 4: Delete votes by seed users
            $this->info('🗑️  Deleting votes from seed users...');
            $deletedUserVotes = DB::table('votes')->whereIn('user_id', $userIdsToDelete)->delete();
            $this->info("   ✅ Deleted {$deletedUserVotes} votes");

            // Step 5: Delete karma history for seed users
            $this->info('🗑️  Deleting karma history from seed users...');
            $deletedKarma = DB::table('karma_histories')->whereIn('user_id', $userIdsToDelete)->delete();
            $this->info("   ✅ Deleted {$deletedKarma} karma history records");

            // Step 6: Delete user achievements for seed users
            $this->info('🗑️  Deleting user achievements from seed users...');
            $deletedAchievements = DB::table('achievement_user')->whereIn('user_id', $userIdsToDelete)->delete();
            $this->info("   ✅ Deleted {$deletedAchievements} user achievements");

            // Step 7: Delete user streaks for seed users (if table exists)
            $this->info('🗑️  Deleting user streaks from seed users...');
            $deletedStreaks = 0;
            if (DB::getSchemaBuilder()->hasTable('user_streaks')) {
                $deletedStreaks = DB::table('user_streaks')->whereIn('user_id', $userIdsToDelete)->delete();
            }
            $this->info("   ✅ Deleted {$deletedStreaks} user streaks");

            // Step 8: Delete role assignments for seed users
            $this->info('🗑️  Deleting role assignments from seed users...');
            $deletedRoles = DB::table('role_user')->whereIn('user_id', $userIdsToDelete)->delete();
            $this->info("   ✅ Deleted {$deletedRoles} role assignments");

            // Step 9: Delete posts
            $this->info('🗑️  Deleting seed posts...');
            $deletedPosts = Post::whereIn('id', $postIdsToDelete)->delete();
            $this->info("   ✅ Deleted {$deletedPosts} posts");

            // Step 10: Delete users
            $this->info('🗑️  Deleting seed users...');
            $deletedUsers = User::whereIn('id', $userIdsToDelete)->delete();
            $this->info("   ✅ Deleted {$deletedUsers} users");

            DB::commit();

            $this->newLine();
            $this->info('✨ Seed data cleanup completed successfully!');

            Log::info('====== SEED DATA CLEANUP COMPLETED ======', [
                'timestamp' => now()->toDateTimeString(),
                'deleted_comments' => $deletedComments + $deletedUserComments,
                'deleted_votes' => $deletedVotes + $deletedUserVotes,
                'deleted_karma' => $deletedKarma,
                'deleted_achievements' => $deletedAchievements,
                'deleted_streaks' => $deletedStreaks,
                'deleted_posts' => $deletedPosts,
                'deleted_users' => $deletedUsers,
            ]);

            return 0;
        } catch (Exception $e) {
            DB::rollBack();

            $this->error('❌ Error during cleanup: ' . $e->getMessage());
            Log::error('Seed data cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }
}
