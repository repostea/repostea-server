<?php

declare(strict_types=1);

use App\Models\Achievement;
use App\Models\Post;
use App\Models\Sub;
use App\Models\User;
use App\Services\AchievementService;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function (): void {
    // Create achievements
    Achievement::create([
        'slug' => 'first_sub',
        'name' => 'achievements.first_sub_title',
        'description' => 'achievements.first_sub_description',
        'icon' => 'fas fa-sitemap',
        'type' => 'subs',
        'requirements' => ['action' => 'create_sub', 'count' => 1],
        'karma_bonus' => 20,
    ]);

    Achievement::create([
        'slug' => 'sub-members-10',
        'name' => 'achievements.sub_members_10',
        'description' => 'achievements.sub_members_10_desc',
        'icon' => 'fas fa-users',
        'type' => 'sub_members',
        'requirements' => ['sub_members' => 10],
        'karma_bonus' => 20,
    ]);

    Achievement::create([
        'slug' => 'sub-posts-10',
        'name' => 'achievements.sub_posts_10',
        'description' => 'achievements.sub_posts_10_desc',
        'icon' => 'fas fa-newspaper',
        'type' => 'sub_posts',
        'requirements' => ['sub_posts' => 10],
        'karma_bonus' => 15,
    ]);

    $this->user = User::factory()->create([
        'karma_points' => 5000,
        'highest_level_id' => 4,
        'created_at' => now()->subDays(40),
    ]);
});

test('user receives first_sub achievement when creating first sub', function (): void {
    Sanctum::actingAs($this->user);

    expect($this->user->achievements()->count())->toBe(0);

    postJson('/api/v1/subs', [
        'name' => 'first-sub',
        'display_name' => 'First Sub',
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    $achievement = Achievement::where('slug', 'first_sub')->first();
    expect($this->user->achievements()->where('achievement_id', $achievement->id)->exists())->toBeTrue();
});

test('creator receives achievement when sub reaches 10 members', function (): void {
    $sub = Sub::create([
        'name' => 'popular-sub',
        'display_name' => 'Popular Sub',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'members_count' => 0,
        'posts_count' => 0,
    ]);

    $sub->load('creator');

    // Add 10 members
    for ($i = 0; $i < 10; $i++) {
        $user = User::factory()->create();
        $sub->subscribers()->attach($user->id);
    }

    $sub->update(['members_count' => 10]);

    // Check achievement manually since we're not going through the controller
    $achievementService = app(AchievementService::class);
    $achievementService->checkSubMemberAchievements($sub);

    $achievement = Achievement::where('slug', 'sub-members-10')->first();
    expect($this->user->achievements()->where('achievement_id', $achievement->id)->exists())->toBeTrue();
});

test('creator receives achievement when sub reaches 10 posts', function (): void {
    $sub = Sub::create([
        'name' => 'active-sub',
        'display_name' => 'Active Sub',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'members_count' => 0,
        'posts_count' => 0,
    ]);

    $sub->load('creator');

    // Create 10 posts
    for ($i = 0; $i < 10; $i++) {
        Post::create([
            'title' => "Test Post {$i}",
            'content' => 'Test content',
            'user_id' => $this->user->id,
            'sub_id' => $sub->id,
            'status' => 'published',
        ]);
    }

    $sub->refresh();

    $achievement = Achievement::where('slug', 'sub-posts-10')->first();
    expect($this->user->achievements()->where('achievement_id', $achievement->id)->exists())->toBeTrue();
});

test('achievement service verifies multiple member thresholds', function (): void {
    $sub = Sub::create([
        'name' => 'mega-sub',
        'display_name' => 'Mega Sub',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'members_count' => 50,
        'posts_count' => 0,
    ]);

    // Create additional achievements for testing
    Achievement::create([
        'slug' => 'sub-members-50',
        'name' => 'achievements.sub_members_50',
        'description' => 'achievements.sub_members_50_desc',
        'icon' => 'fas fa-users',
        'type' => 'sub_members',
        'requirements' => ['sub_members' => 50],
        'karma_bonus' => 50,
    ]);

    $sub->load('creator');

    $achievementService = app(AchievementService::class);
    $achievementService->checkSubMemberAchievements($sub);

    // Should have both 10 and 50 member achievements
    $achievement10 = Achievement::where('slug', 'sub-members-10')->first();
    $achievement50 = Achievement::where('slug', 'sub-members-50')->first();

    expect($this->user->achievements()->where('achievement_id', $achievement10->id)->exists())->toBeTrue();
    expect($this->user->achievements()->where('achievement_id', $achievement50->id)->exists())->toBeTrue();
});

test('achievement does not unlock if threshold not reached', function (): void {
    $sub = Sub::create([
        'name' => 'small-sub',
        'display_name' => 'Small Sub',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'members_count' => 5,
        'posts_count' => 5,
    ]);

    $sub->load('creator');

    $achievementService = app(AchievementService::class);
    $achievementService->checkSubAchievements($sub);

    $achievementMembers = Achievement::where('slug', 'sub-members-10')->first();
    $achievementPosts = Achievement::where('slug', 'sub-posts-10')->first();

    expect($this->user->achievements()->where('achievement_id', $achievementMembers->id)->exists())->toBeFalse();
    expect($this->user->achievements()->where('achievement_id', $achievementPosts->id)->exists())->toBeFalse();
});
