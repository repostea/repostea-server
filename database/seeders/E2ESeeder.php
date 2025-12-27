<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Sub;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal seeder for E2E tests.
 * Only includes essential data needed for tests to run.
 */
final class E2ESeeder extends Seeder
{
    /**
     * Tables with test-generated data that should be truncated.
     * Reference tables (roles, karma_levels, etc.) are NOT truncated.
     */
    private array $tablesToTruncate = [
        'activity_pub_blocked_instances',
        'activity_pub_delivery_logs',
        'achievements_user',
        'api_tokens',
        'bookmarks',
        'comment_votes',
        'comments',
        'follows',
        'invitations',
        'karma_events',
        'lists',
        'list_posts',
        'media',
        'moderation_logs',
        'notifications',
        'oauth_tokens',
        'personal_access_tokens',
        'poll_options',
        'poll_votes',
        'polls',
        'post_tag',
        'post_views',
        'post_votes',
        'posts',
        'push_subscriptions',
        'read_notifications',
        'reports',
        'sessions',
        'sub_bans',
        'sub_members',
        'subs',
        'user_achievements',
        'user_preferences',
        'users',
    ];

    public function run(): void
    {
        $this->truncateTestData();

        $this->call([
            RoleSeeder::class,
            KarmaLevelSeeder::class,
            AchievementsSeeder::class,
            CategorySeeder::class,
            TagCategoriesAndTagsSeeder::class,
        ]);

        $this->createTestData();
    }

    /**
     * Create essential test data (admin user and default sub).
     */
    private function createTestData(): void
    {
        // Create admin user for E2E tests
        $admin = User::create([
            'username' => 'e2e_admin',
            'email' => 'e2e_admin@example.com',
            'password' => Hash::make('TestPassword123!'),
            'email_verified_at' => now(),
            'karma_points' => 1000,
        ]);

        // Create default test sub
        Sub::create([
            'name' => 'test',
            'display_name' => 'Test Community',
            'description' => 'Default community for E2E tests',
            'created_by' => $admin->id,
            'is_private' => false,
            'is_adult' => false,
            'visibility' => 'visible',
        ]);
    }

    /**
     * Truncate tables with test data, preserving reference data.
     */
    private function truncateTestData(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($this->tablesToTruncate as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
