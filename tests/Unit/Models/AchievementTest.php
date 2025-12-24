<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Achievement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AchievementTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_an_achievement(): void
    {
        $achievement = Achievement::create([
            'name' => 'First Post',
            'slug' => 'first-post',
            'description' => 'Created your first post',
            'icon' => 'trophy',
            'type' => 'post',
            'requirements' => ['posts_count' => 1],
            'karma_bonus' => 10,
        ]);

        $this->assertInstanceOf(Achievement::class, $achievement);
        $this->assertEquals('First Post', $achievement->name);
        $this->assertEquals('first-post', $achievement->slug);
        $this->assertEquals('post', $achievement->type);
        $this->assertEquals(10, $achievement->karma_bonus);
    }

    public function test_it_casts_requirements_to_array(): void
    {
        $achievement = Achievement::create([
            'name' => 'Veteran',
            'slug' => 'veteran',
            'description' => 'Been active for 30 days',
            'type' => 'streak',
            'requirements' => ['days_active' => 30, 'min_karma' => 100],
        ]);

        $this->assertIsArray($achievement->requirements);
        $this->assertEquals(['days_active' => 30, 'min_karma' => 100], $achievement->requirements);
    }

    public function test_it_belongs_to_many_users(): void
    {
        $achievement = Achievement::create([
            'name' => 'Commentator',
            'slug' => 'commentator',
            'description' => 'Posted 10 comments',
            'type' => 'comment',
            'requirements' => ['comments_count' => 10],
        ]);

        $user = User::factory()->create();

        $achievement->users()->attach($user->id, [
            'progress' => 100,
            'unlocked_at' => now(),
        ]);

        $this->assertEquals(1, $achievement->users()->count());
        $this->assertInstanceOf(User::class, $achievement->users->first());
    }

    public function test_it_stores_pivot_data_with_users(): void
    {
        $achievement = Achievement::create([
            'name' => 'Helper',
            'slug' => 'helper',
            'description' => 'Helped 5 users',
            'type' => 'vote',
            'requirements' => ['helpful_votes' => 5],
        ]);

        $user = User::factory()->create();
        $unlockedAt = now();

        $achievement->users()->attach($user->id, [
            'progress' => 80,
            'unlocked_at' => $unlockedAt,
        ]);

        $attachedUser = $achievement->users()->first();

        $this->assertEquals(80, $attachedUser->pivot->progress);
        $this->assertNotNull($attachedUser->pivot->unlocked_at);
    }

    public function test_it_has_translated_name_attribute(): void
    {
        $achievement = Achievement::create([
            'name' => 'achievements.first_post',
            'slug' => 'first-post',
            'description' => 'Created your first post',
            'type' => 'post',
            'requirements' => ['posts_count' => 1],
        ]);

        $this->assertArrayHasKey('translated_name', $achievement->toArray());
    }

    public function test_it_has_translated_description_attribute(): void
    {
        $achievement = Achievement::create([
            'name' => 'First Post',
            'slug' => 'first-post',
            'description' => 'achievements.first_post_description',
            'type' => 'post',
            'requirements' => ['posts_count' => 1],
        ]);

        $this->assertArrayHasKey('translated_description', $achievement->toArray());
    }

    public function test_it_handles_empty_requirements(): void
    {
        $achievement = Achievement::create([
            'name' => 'Special',
            'slug' => 'special',
            'description' => 'Special achievement',
            'type' => 'special',
            'requirements' => [],
        ]);

        $this->assertIsArray($achievement->requirements);
        $this->assertEmpty($achievement->requirements);
    }

    public function test_it_stores_complex_requirements(): void
    {
        $achievement = Achievement::create([
            'name' => 'Power User',
            'slug' => 'power-user',
            'description' => 'Active power user',
            'type' => 'karma',
            'requirements' => [
                'posts_count' => 100,
                'comments_count' => 500,
                'karma' => 1000,
                'days_active' => 90,
                'followers' => 50,
            ],
        ]);

        $this->assertEquals(100, $achievement->requirements['posts_count']);
        $this->assertEquals(500, $achievement->requirements['comments_count']);
        $this->assertEquals(1000, $achievement->requirements['karma']);
        $this->assertEquals(90, $achievement->requirements['days_active']);
        $this->assertEquals(50, $achievement->requirements['followers']);
    }

    public function test_it_can_store_icon(): void
    {
        $achievement = Achievement::create([
            'name' => 'Expert',
            'slug' => 'expert',
            'description' => 'Expert badge',
            'icon' => 'star',
            'type' => 'action',
            'requirements' => ['expertise_score' => 100],
        ]);

        $this->assertEquals('star', $achievement->icon);
    }

    public function test_it_can_store_karma_bonus(): void
    {
        $achievement = Achievement::create([
            'name' => 'Bonus Award',
            'slug' => 'bonus-award',
            'description' => 'Get bonus karma',
            'type' => 'special',
            'requirements' => ['action' => 'complete'],
            'karma_bonus' => 500,
        ]);

        $this->assertEquals(500, $achievement->karma_bonus);
    }

    public function test_it_has_timestamps(): void
    {
        $achievement = Achievement::create([
            'name' => 'Timed',
            'slug' => 'timed',
            'description' => 'Time tracking test',
            'type' => 'registration',
            'requirements' => [],
        ]);

        $this->assertNotNull($achievement->created_at);
        $this->assertNotNull($achievement->updated_at);
    }

    public function test_multiple_users_can_have_same_achievement(): void
    {
        $achievement = Achievement::create([
            'name' => 'Popular',
            'slug' => 'popular',
            'description' => 'Popular achievement',
            'type' => 'posts',
            'requirements' => ['likes' => 100],
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $achievement->users()->attach($user1->id, [
            'progress' => 100,
            'unlocked_at' => now(),
        ]);

        $achievement->users()->attach($user2->id, [
            'progress' => 100,
            'unlocked_at' => now(),
        ]);

        $this->assertEquals(2, $achievement->users()->count());
    }

    public function test_user_can_have_multiple_achievements(): void
    {
        $achievement1 = Achievement::create([
            'name' => 'Achievement 1',
            'slug' => 'achievement-1',
            'description' => 'First achievement',
            'type' => 'comments',
            'requirements' => [],
        ]);

        $achievement2 = Achievement::create([
            'name' => 'Achievement 2',
            'slug' => 'achievement-2',
            'description' => 'Second achievement',
            'type' => 'posts',
            'requirements' => [],
        ]);

        $user = User::factory()->create();

        $achievement1->users()->attach($user->id, [
            'progress' => 100,
            'unlocked_at' => now(),
        ]);

        $achievement2->users()->attach($user->id, [
            'progress' => 100,
            'unlocked_at' => now(),
        ]);

        $this->assertEquals(2, $user->fresh()->achievements()->count());
    }

    public function test_it_can_track_progress_before_unlock(): void
    {
        $achievement = Achievement::create([
            'name' => 'Progress Tracker',
            'slug' => 'progress-tracker',
            'description' => 'Track progress',
            'type' => 'karma',
            'requirements' => ['target' => 100],
        ]);

        $user = User::factory()->create();

        $achievement->users()->attach($user->id, [
            'progress' => 50,
            'unlocked_at' => null,
        ]);

        $attachedUser = $achievement->users()->first();

        $this->assertEquals(50, $attachedUser->pivot->progress);
        $this->assertNull($attachedUser->pivot->unlocked_at);
    }
}
