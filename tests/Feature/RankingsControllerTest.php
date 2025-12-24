<?php

declare(strict_types=1);

use App\Models\Achievement;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\UserStreak;
use App\Models\Vote;

use function Pest\Laravel\getJson;

test('karma ranking returns users ordered by karma', function (): void {
    $topUser = User::factory()->create(['username' => 'top_user', 'karma_points' => 1000, 'is_guest' => false]);
    $midUser = User::factory()->create(['username' => 'mid_user', 'karma_points' => 500, 'is_guest' => false]);
    $lowUser = User::factory()->create(['username' => 'low_user', 'karma_points' => 100, 'is_guest' => false]);

    // Users need at least one interaction to appear in rankings
    Post::factory()->create(['user_id' => $topUser->id]);
    Post::factory()->create(['user_id' => $midUser->id]);
    Post::factory()->create(['user_id' => $lowUser->id]);

    $response = getJson('/api/v1/rankings/karma');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'users' => [
                '*' => ['id', 'username', 'karma_points', 'level'],
            ],
            'pagination',
        ],
        'timeframe',
    ]);

    $users = $response->json('data.users');
    expect($users[0]['username'])->toBe('top_user');
    expect($users[0]['karma_points'])->toBe(1000);
});

test('karma ranking excludes guest users', function (): void {
    $normalUser = User::factory()->create(['karma_points' => 500, 'is_guest' => false]);
    $guestUser = User::factory()->create(['karma_points' => 1000, 'is_guest' => true]);

    // Both users have interactions, but guest should be excluded
    Post::factory()->create(['user_id' => $normalUser->id]);
    Post::factory()->create(['user_id' => $guestUser->id]);

    $response = getJson('/api/v1/rankings/karma');

    $response->assertStatus(200);
    $users = $response->json('data.users');

    foreach ($users as $user) {
        expect($user['karma_points'])->not->toBe(1000);
    }
});

test('karma ranking excludes users without karma', function (): void {
    $userWithKarma = User::factory()->create(['karma_points' => 500, 'is_guest' => false]);
    $userWithoutKarma = User::factory()->create(['karma_points' => 0, 'is_guest' => false]);

    // Both users have interactions
    Post::factory()->create(['user_id' => $userWithKarma->id]);
    Post::factory()->create(['user_id' => $userWithoutKarma->id]);

    $response = getJson('/api/v1/rankings/karma');

    $response->assertStatus(200);
    $users = $response->json('data.users');

    expect(count($users))->toBe(1);
    expect($users[0]['karma_points'])->toBe(500);
});

test('karma ranking respects maximum limit of 100', function (): void {
    // Create users with posts so they have interactions
    User::factory()->count(150)->create(['karma_points' => 100, 'is_guest' => false])
        ->each(fn ($user) => Post::factory()->create(['user_id' => $user->id]));

    $response = getJson('/api/v1/rankings/karma?limit=200');

    $response->assertStatus(200);
    $users = $response->json('data.users');

    expect(count($users))->toBeLessThanOrEqual(100);
});

test('karma ranking supports pagination', function (): void {
    // Create users with posts so they have interactions
    User::factory()->count(25)->create(['karma_points' => 100, 'is_guest' => false])
        ->each(fn ($user) => Post::factory()->create(['user_id' => $user->id]));

    $response = getJson('/api/v1/rankings/karma?limit=10&page=2');

    $response->assertStatus(200);
    $response->assertJsonPath('data.pagination.current_page', 2);
    $response->assertJsonPath('data.pagination.per_page', 10);
});

test('karma ranking excludes users without interactions', function (): void {
    // User with karma but no interactions (no votes, comments, or posts)
    // Even though they have high karma, they should NOT appear
    User::factory()->create(['username' => 'inactive_user', 'karma_points' => 1000, 'is_guest' => false]);

    // User with karma and a post
    $activeUser = User::factory()->create(['username' => 'active_user', 'karma_points' => 500, 'is_guest' => false]);
    $post = Post::factory()->create(['user_id' => $activeUser->id]);

    // User with karma and a comment (on the existing post to avoid creating extra users)
    $commenter = User::factory()->create(['username' => 'commenter', 'karma_points' => 300, 'is_guest' => false]);
    Comment::factory()->create(['user_id' => $commenter->id, 'post_id' => $post->id]);

    // User with karma and a vote (on the existing post)
    $voter = User::factory()->create(['username' => 'voter', 'karma_points' => 200, 'is_guest' => false]);
    Vote::factory()->create(['user_id' => $voter->id, 'votable_id' => $post->id, 'votable_type' => Post::class]);

    $response = getJson('/api/v1/rankings/karma');

    $response->assertStatus(200);
    $users = $response->json('data.users');
    $usernames = collect($users)->pluck('username')->toArray();

    // The inactive_user should NOT be in the results despite having the highest karma
    expect($usernames)->not->toContain('inactive_user');

    // Active users should be in the results
    expect($usernames)->toContain('active_user');
    expect($usernames)->toContain('commenter');
    expect($usernames)->toContain('voter');
});

test('posts ranking returns users ordered by post count', function (): void {
    $user1 = User::factory()->create(['username' => 'prolific', 'is_guest' => false]);
    $user2 = User::factory()->create(['username' => 'casual', 'is_guest' => false]);

    Post::factory()->count(10)->create(['user_id' => $user1->id]);
    Post::factory()->count(3)->create(['user_id' => $user2->id]);

    $response = getJson('/api/v1/rankings/posts');

    $response->assertStatus(200);
    $users = $response->json('data.users');

    expect($users[0]['username'])->toBe('prolific');
    expect($users[0]['posts_count'])->toBe(10);
});

test('posts ranking excludes users without posts', function (): void {
    $user1 = User::factory()->create(['is_guest' => false]);
    $user2 = User::factory()->create(['is_guest' => false]);

    Post::factory()->count(5)->create(['user_id' => $user1->id]);

    $response = getJson('/api/v1/rankings/posts');

    $response->assertStatus(200);
    $users = $response->json('data.users');

    expect(count($users))->toBe(1);
});

test('posts ranking respeta timeframe', function (): void {
    $user1 = User::factory()->create(['username' => 'recent', 'is_guest' => false]);
    $user2 = User::factory()->create(['username' => 'old', 'is_guest' => false]);

    Post::factory()->count(5)->create(['user_id' => $user1->id, 'created_at' => now()]);
    Post::factory()->count(10)->create(['user_id' => $user2->id, 'created_at' => now()->subMonths(2)]);

    $response = getJson('/api/v1/rankings/posts?timeframe=month');

    $response->assertStatus(200);
    $users = $response->json('data.users');

    if (count($users) > 0) {
        expect($users[0]['username'])->toBe('recent');
    }
});

test('comments ranking returns users ordered by comment count', function (): void {
    $user1 = User::factory()->create(['username' => 'chatty', 'is_guest' => false]);
    $user2 = User::factory()->create(['username' => 'quiet', 'is_guest' => false]);

    Comment::factory()->count(20)->create(['user_id' => $user1->id]);
    Comment::factory()->count(5)->create(['user_id' => $user2->id]);

    $response = getJson('/api/v1/rankings/comments');

    $response->assertStatus(200);
    $users = $response->json('data.users');

    expect($users[0]['username'])->toBe('chatty');
    expect($users[0]['comments_count'])->toBe(20);
});

test('comments ranking excludes users without comments', function (): void {
    $user1 = User::factory()->create(['is_guest' => false]);
    $user2 = User::factory()->create(['is_guest' => false]);

    Comment::factory()->count(10)->create(['user_id' => $user1->id]);

    $response = getJson('/api/v1/rankings/comments');

    $response->assertStatus(200);
    $users = $response->json('data.users');

    expect(count($users))->toBe(1);
});

test('streaks ranking returns users ordered by longest_streak', function (): void {
    $user1 = User::factory()->create(['username' => 'consistent', 'is_guest' => false]);
    $user2 = User::factory()->create(['username' => 'inconsistent', 'is_guest' => false]);

    UserStreak::create([
        'user_id' => $user1->id,
        'current_streak' => 10,
        'longest_streak' => 30,
        'last_activity_date' => now(),
    ]);

    UserStreak::create([
        'user_id' => $user2->id,
        'current_streak' => 3,
        'longest_streak' => 10,
        'last_activity_date' => now(),
    ]);

    $response = getJson('/api/v1/rankings/streaks');

    $response->assertStatus(200);
    $users = $response->json('data.users');

    expect($users[0]['username'])->toBe('consistent');
    expect($users[0]['longest_streak'])->toBe(30);
});

test('streaks ranking excludes users without streaks', function (): void {
    $user1 = User::factory()->create(['is_guest' => false]);
    $user2 = User::factory()->create(['is_guest' => false]);

    UserStreak::create([
        'user_id' => $user1->id,
        'current_streak' => 5,
        'longest_streak' => 10,
        'last_activity_date' => now(),
    ]);

    $response = getJson('/api/v1/rankings/streaks');

    $response->assertStatus(200);
    $users = $response->json('data.users');

    expect(count($users))->toBe(1);
});

test('achievements ranking returns users ordered by achievement count', function (): void {
    $user1 = User::factory()->create(['username' => 'achiever', 'is_guest' => false]);
    $user2 = User::factory()->create(['username' => 'beginner', 'is_guest' => false]);

    $achievement1 = Achievement::create([
        'slug' => 'test-1',
        'name' => 'Test 1',
        'description' => 'Test',
        'icon' => '🏆',
        'type' => 'karma',
        'karma_bonus' => 10,
        'requirements' => json_encode(['test' => 1]),
    ]);

    $achievement2 = Achievement::create([
        'slug' => 'test-2',
        'name' => 'Test 2',
        'description' => 'Test',
        'icon' => '🏆',
        'type' => 'karma',
        'karma_bonus' => 10,
        'requirements' => json_encode(['test' => 1]),
    ]);

    $user1->achievements()->attach($achievement1->id, ['progress' => 100, 'unlocked_at' => now()]);
    $user1->achievements()->attach($achievement2->id, ['progress' => 100, 'unlocked_at' => now()]);
    $user2->achievements()->attach($achievement1->id, ['progress' => 100, 'unlocked_at' => now()]);

    $response = getJson('/api/v1/rankings/achievements');

    $response->assertStatus(200);
    $users = $response->json('data.users');

    expect($users[0]['username'])->toBe('achiever');
    expect($users[0]['achievements_count'])->toBe(2);
});

test('achievements ranking excludes non-unlocked achievements', function (): void {
    $user = User::factory()->create(['is_guest' => false]);

    $achievement = Achievement::create([
        'slug' => 'test',
        'name' => 'Test',
        'description' => 'Test',
        'icon' => '🏆',
        'type' => 'karma',
        'karma_bonus' => 10,
        'requirements' => json_encode(['test' => 1]),
    ]);

    // Attach but not unlocked
    $user->achievements()->attach($achievement->id, ['progress' => 50, 'unlocked_at' => null]);

    $response = getJson('/api/v1/rankings/achievements');

    $response->assertStatus(200);
    $users = $response->json('data.users');

    expect(count($users))->toBe(0);
});

test('userKarmaHistory returns user karma history', function (): void {
    $user = User::factory()->create();

    // Insert karma history
    DB::table('daily_karma_stats')->insert([
        ['user_id' => $user->id, 'date' => now()->subDays(5)->toDateString(), 'karma_earned' => 10],
        ['user_id' => $user->id, 'date' => now()->subDays(4)->toDateString(), 'karma_earned' => 15],
        ['user_id' => $user->id, 'date' => now()->subDays(3)->toDateString(), 'karma_earned' => 20],
    ]);

    $response = getJson("/api/v1/users/{$user->id}/karma-history");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'history',
            'stats' => [
                'total_karma_period',
                'average_daily',
                'best_day',
                'days_active',
            ],
        ],
    ]);

    expect($response->json('data.stats.total_karma_period'))->toBe(45);
    expect($response->json('data.stats.days_active'))->toBe(3);
});

test('userKarmaHistory respects day limit', function (): void {
    $user = User::factory()->create();

    // Insert 50 days of karma history
    for ($i = 0; $i < 50; $i++) {
        DB::table('daily_karma_stats')->insert([
            'user_id' => $user->id,
            'date' => now()->subDays($i)->toDateString(),
            'karma_earned' => 10,
        ]);
    }

    $response = getJson("/api/v1/users/{$user->id}/karma-history?days=30");

    $response->assertStatus(200);
    $history = $response->json('data.history');

    expect(count($history))->toBe(30);
});

test('userKarmaHistory calculates best day correctly', function (): void {
    $user = User::factory()->create();

    DB::table('daily_karma_stats')->insert([
        ['user_id' => $user->id, 'date' => now()->subDays(3)->toDateString(), 'karma_earned' => 10],
        ['user_id' => $user->id, 'date' => now()->subDays(2)->toDateString(), 'karma_earned' => 50],
        ['user_id' => $user->id, 'date' => now()->subDays(1)->toDateString(), 'karma_earned' => 15],
    ]);

    $response = getJson("/api/v1/users/{$user->id}/karma-history");

    $response->assertStatus(200);
    expect($response->json('data.stats.best_day.karma'))->toBe(50);
});
