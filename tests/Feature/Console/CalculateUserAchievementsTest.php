<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Achievement;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CalculateUserAchievementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create basic achievements needed for tests using firstOrCreate
        Achievement::firstOrCreate(
            ['slug' => 'welcome'],
            [
                'name' => 'achievements.welcome_title',
                'description' => 'achievements.welcome_description',
                'icon' => 'fas fa-user',
                'type' => 'special',
                'requirements' => ['action' => 'register'],
                'karma_bonus' => 10,
            ],
        );

        Achievement::firstOrCreate(
            ['slug' => 'first_post'],
            [
                'name' => 'achievements.first_post_title',
                'description' => 'achievements.first_post_description',
                'icon' => 'fas fa-pen',
                'type' => 'posts',
                'requirements' => ['posts' => 1],
                'karma_bonus' => 5,
            ],
        );

        Achievement::firstOrCreate(
            ['slug' => 'posts_10'],
            [
                'name' => 'achievements.posts_10_title',
                'description' => 'achievements.posts_10_description',
                'icon' => 'fas fa-edit',
                'type' => 'posts',
                'requirements' => ['posts' => 10],
                'karma_bonus' => 10,
            ],
        );

        Achievement::firstOrCreate(
            ['slug' => 'first_comment'],
            [
                'name' => 'achievements.first_comment_title',
                'description' => 'achievements.first_comment_description',
                'icon' => 'fas fa-comment',
                'type' => 'comments',
                'requirements' => ['comments' => 1],
                'karma_bonus' => 5,
            ],
        );

        Achievement::firstOrCreate(
            ['slug' => 'comments_10'],
            [
                'name' => 'achievements.comments_10_title',
                'description' => 'achievements.comments_10_description',
                'icon' => 'fas fa-comment',
                'type' => 'comments',
                'requirements' => ['comments' => 10],
                'karma_bonus' => 10,
            ],
        );

        Achievement::firstOrCreate(
            ['slug' => 'first_vote'],
            [
                'name' => 'achievements.first_vote_title',
                'description' => 'achievements.first_vote_description',
                'icon' => 'fas fa-thumbs-up',
                'type' => 'vote',
                'requirements' => ['votes' => 1],
                'karma_bonus' => 5,
            ],
        );

        Achievement::firstOrCreate(
            ['slug' => 'votes_10'],
            [
                'name' => 'achievements.votes_10_title',
                'description' => 'achievements.votes_10_description',
                'icon' => 'fas fa-thumbs-up',
                'type' => 'vote',
                'requirements' => ['votes' => 10],
                'karma_bonus' => 10,
            ],
        );
    }

    public function test_it_unlocks_welcome_achievement_for_all_users(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $this->artisan('achievements:calculate --user=' . $user->id)
            ->assertExitCode(0);

        // Assert
        $this->assertTrue($user->achievements()->where('slug', 'welcome')->exists());
        $achievement = $user->achievements()->where('slug', 'welcome')->first();
        $this->assertNotNull($achievement->pivot->unlocked_at);
    }

    public function test_it_unlocks_first_post_achievement(): void
    {
        // Arrange
        $user = User::factory()->create();
        Post::factory()->create(['user_id' => $user->id]);

        // Act
        $this->artisan('achievements:calculate --user=' . $user->id)
            ->assertExitCode(0);

        // Assert
        $this->assertTrue($user->achievements()->where('slug', 'first_post')->exists());
        $achievement = $user->achievements()->where('slug', 'first_post')->first();
        $this->assertNotNull($achievement->pivot->unlocked_at);
    }

    public function test_it_unlocks_posts_milestone_achievements(): void
    {
        // Arrange
        $user = User::factory()->create();
        Post::factory()->count(10)->create(['user_id' => $user->id]);

        // Act
        $this->artisan('achievements:calculate --user=' . $user->id)
            ->assertExitCode(0);

        // Assert - should have both first_post and posts_10
        $this->assertTrue($user->achievements()->where('slug', 'first_post')->exists());
        $this->assertTrue($user->achievements()->where('slug', 'posts_10')->exists());
        $achievement = $user->achievements()->where('slug', 'posts_10')->first();
        $this->assertNotNull($achievement->pivot->unlocked_at);
    }

    public function test_it_processes_all_users_with_all_flag(): void
    {
        // Arrange
        $users = User::factory()->count(3)->create();

        // Act
        $this->artisan('achievements:calculate --all')
            ->expectsOutput('Starting achievement calculation...')
            ->assertExitCode(0);

        // Assert - all users should have welcome achievement
        foreach ($users as $user) {
            $this->assertTrue($user->achievements()->where('slug', 'welcome')->exists());
        }
    }

    public function test_it_does_not_duplicate_already_unlocked_achievements(): void
    {
        // Arrange - create user after 2026 to avoid early_adopter achievement
        // Also set karma_points to 0 to avoid karma-based achievements
        $user = User::factory()->create(['created_at' => now()->addYear(2), 'karma_points' => 0]);
        $achievement = Achievement::where('slug', 'welcome')->first();

        // Manually unlock achievement
        $user->achievements()->attach($achievement->id, [
            'progress' => 100,
            'unlocked_at' => now()->subDay(),
        ]);

        $initialCount = $user->achievements()->count();

        // Act
        $this->artisan('achievements:calculate --user=' . $user->id)
            ->assertExitCode(0);

        // Refresh user to get latest data
        $user->refresh();

        // Assert - count should be the same (welcome achievement should not be duplicated)
        $this->assertEquals($initialCount, $user->achievements()->count());

        // Verify the welcome achievement is still there and only once
        $this->assertEquals(1, $user->achievements()->where('slug', 'welcome')->count());
    }

    public function test_it_awards_karma_bonus_when_unlocking_achievement(): void
    {
        // Arrange
        $user = User::factory()->create(['karma_points' => 0]);
        Post::factory()->count(5)->create(['user_id' => $user->id]);

        // Act
        $this->artisan('achievements:calculate --user=' . $user->id)
            ->assertExitCode(0);

        // Assert - user should have karma from achievements
        $user->refresh();
        $this->assertGreaterThan(0, $user->karma_points);
    }

    public function test_force_flag_recalculates_already_unlocked_achievements(): void
    {
        // Arrange
        $user = User::factory()->create();
        $achievement = Achievement::where('slug', 'welcome')->first();

        // Manually unlock achievement
        $user->achievements()->attach($achievement->id, [
            'progress' => 100,
            'unlocked_at' => now()->subDay(),
        ]);

        // Act
        $this->artisan('achievements:calculate --user=' . $user->id . ' --force')
            ->assertExitCode(0);

        // Assert - should still be unlocked
        $this->assertTrue($user->achievements()->where('slug', 'welcome')->exists());
    }

    public function test_it_requires_either_user_or_all_or_recent_flag(): void
    {
        // Act & Assert
        $this->artisan('achievements:calculate')
            ->expectsOutput('You must specify either --user=ID, --all, or --recent=HOURS')
            ->assertExitCode(1);
    }

    public function test_it_processes_users_with_recent_activity(): void
    {
        // Arrange - create user with recent post
        $userWithRecentPost = User::factory()->create();
        Post::factory()->create([
            'user_id' => $userWithRecentPost->id,
            'created_at' => now()->subMinutes(30), // 30 minutes ago
        ]);

        // Create user with old post
        $userWithOldPost = User::factory()->create();
        Post::factory()->create([
            'user_id' => $userWithOldPost->id,
            'created_at' => now()->subHours(2), // 2 hours ago
        ]);

        // Act - process users with activity in last 1 hour
        $this->artisan('achievements:calculate --recent=1')
            ->assertExitCode(0);

        // Assert - only user with recent activity should have first_post
        $this->assertTrue(
            $userWithRecentPost->achievements()->where('slug', 'first_post')->exists(),
        );
        $this->assertFalse(
            $userWithOldPost->achievements()->where('slug', 'first_post')->exists(),
        );
    }

    public function test_recent_flag_filters_by_comments(): void
    {
        // Arrange
        $user = User::factory()->create();
        $post = Post::factory()->create();

        // Create a comment factory if it doesn't exist, or use direct model creation
        \App\Models\Comment::factory()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'content' => 'Test comment',
            'created_at' => now()->subMinutes(30),
        ]);

        // Act
        $this->artisan('achievements:calculate --recent=1')
            ->assertExitCode(0);

        // Assert
        $this->assertTrue($user->achievements()->where('slug', 'first_comment')->exists());
    }

    public function test_it_processes_users_in_chunks(): void
    {
        // Arrange - create 150 users (more than chunk size of 100)
        User::factory()->count(150)->create();
        $totalUsers = User::count();

        // Act
        $this->artisan('achievements:calculate --all')
            ->expectsOutputToContain("Processing {$totalUsers} user(s)...")
            ->assertExitCode(0);

        // Assert - all users should have welcome achievement
        $usersWithWelcome = User::whereHas('achievements', function ($query): void {
            $query->where('slug', 'welcome');
        })->count();

        $this->assertEquals($totalUsers, $usersWithWelcome);
    }

    public function test_recent_flag_defaults_to_one_hour(): void
    {
        // Arrange
        $user = User::factory()->create();
        Post::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subMinutes(30),
        ]);

        // Act - use --recent without value (should default to 1)
        $this->artisan('achievements:calculate --recent')
            ->assertExitCode(0);

        // Assert
        $this->assertTrue($user->achievements()->where('slug', 'first_post')->exists());
    }
}
