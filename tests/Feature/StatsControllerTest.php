<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\Vote;

use function Pest\Laravel\getJson;

test('general returns general statistics', function (): void {
    // Create test data
    User::factory()->count(10)->create();
    User::factory()->count(3)->create(['updated_at' => now()]);
    Post::factory()->count(15)->create();
    Post::factory()->count(5)->create(['created_at' => now()]);
    Comment::factory()->count(25)->create();
    Comment::factory()->count(8)->create(['created_at' => now()]);

    $response = getJson('/api/v1/stats/general');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'total_users',
        'active_users_today',
        'total_posts',
        'total_comments',
        'posts_today',
        'comments_today',
    ]);

    expect($response->json('total_users'))->toBeGreaterThanOrEqual(10);
    expect($response->json('total_posts'))->toBeGreaterThanOrEqual(15);
    expect($response->json('total_comments'))->toBeGreaterThanOrEqual(25);
});

test('content returns content statistics', function (): void {
    // Create posts with different statuses
    Post::factory()->count(10)->create(['status' => 'published']);
    Post::factory()->count(3)->create(['status' => 'pending']);
    Post::factory()->count(2)->create(['created_at' => now()->subHours(12)]);
    Post::factory()->count(4)->create(['created_at' => now()->subDays(3)]);

    $response = getJson('/api/v1/stats/content');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'published_posts',
        'pending_posts',
        'posts_last_24h',
        'posts_last_7d',
        'posts_by_language',
        'popular_tags',
    ]);

    expect($response->json('published_posts'))->toBeGreaterThanOrEqual(10);
    expect($response->json('pending_posts'))->toBeGreaterThanOrEqual(3);
});

test('content groups posts by language', function (): void {
    Post::factory()->count(5)->create(['language_code' => 'es']);
    Post::factory()->count(3)->create(['language_code' => 'en']);
    Post::factory()->count(2)->create(['language_code' => 'fr']);

    $response = getJson('/api/v1/stats/content');

    $response->assertStatus(200);
    $postsByLanguage = $response->json('posts_by_language');

    expect($postsByLanguage)->toBeArray();
    expect(count($postsByLanguage))->toBeGreaterThanOrEqual(3);
});

test('users returns user statistics', function (): void {
    // Create users with different dates
    User::factory()->count(20)->create();
    User::factory()->count(5)->create([
        'email_verified_at' => now(),
        'created_at' => now(),
    ]);
    User::factory()->count(3)->create([
        'email_verified_at' => now(),
        'updated_at' => now(),
    ]);
    User::factory()->count(4)->create([
        'email_verified_at' => now(),
        'created_at' => now()->subDays(3),
    ]);

    $response = getJson('/api/v1/stats/users');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'total_users',
        'active_users_today',
        'active_users_week',
        'active_users_month',
        'new_users_today',
        'new_users_week',
        'new_users_month',
        'verified_users',
        'verification_rate',
        'top_karma_users',
    ]);

    expect($response->json('total_users'))->toBeGreaterThanOrEqual(20);
});

test('users calculates verification rate correctly', function (): void {
    // Create 10 users, 7 verified
    User::factory()->count(7)->create(['email_verified_at' => now()]);
    User::factory()->count(3)->create(['email_verified_at' => null]);

    $response = getJson('/api/v1/stats/users');

    $response->assertStatus(200);
    $rate = $response->json('verification_rate');
    expect($rate)->toBeGreaterThanOrEqual(60);
    expect($rate)->toBeLessThanOrEqual(80);
});

test('users returns top karma users', function (): void {
    User::factory()->create(['username' => 'top_user', 'karma_points' => 1000]);
    User::factory()->create(['username' => 'mid_user', 'karma_points' => 500]);
    User::factory()->create(['username' => 'low_user', 'karma_points' => 100]);

    $response = getJson('/api/v1/stats/users');

    $response->assertStatus(200);
    $topUsers = $response->json('top_karma_users');

    expect($topUsers)->toBeArray();
    expect($topUsers[0]['username'])->toBe('top_user');
    expect($topUsers[0]['karma_points'])->toBe(1000);
});

test('engagement returns engagement statistics', function (): void {
    $user = User::factory()->create();
    $post1 = Post::factory()->create();
    $post2 = Post::factory()->create();

    // Create votes
    Vote::create([
        'user_id' => $user->id,
        'votable_type' => 'App\Models\Post',
        'votable_id' => $post1->id,
        'value' => 1,
        'type' => 'upvote',
        'created_at' => now()->subHours(12),
    ]);

    Vote::create([
        'user_id' => $user->id,
        'votable_type' => 'App\Models\Post',
        'votable_id' => $post2->id,
        'value' => 1,
        'type' => 'upvote',
        'created_at' => now()->subDays(3),
    ]);

    // Create comments
    Comment::factory()->count(5)->create(['created_at' => now()->subHours(12)]);
    Comment::factory()->count(3)->create(['created_at' => now()->subDays(3)]);

    $response = getJson('/api/v1/stats/engagement');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'total_votes',
        'votes_last_24h',
        'votes_last_7d',
        'comments_last_24h',
        'comments_last_7d',
        'avg_comments_per_post',
        'avg_votes_per_post',
    ]);

    expect($response->json('total_votes'))->toBeGreaterThanOrEqual(2);
    expect($response->json('votes_last_24h'))->toBeGreaterThanOrEqual(1);
});

test('engagement calcula promedios correctamente', function (): void {
    // Create 5 posts
    $posts = Post::factory()->count(5)->create();

    // Create 10 comments (avg 2 per post)
    Comment::factory()->count(10)->create([
        'post_id' => $posts->random()->id,
    ]);

    // Create votes from different users to avoid unique constraint
    foreach ($posts as $index => $post) {
        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            Vote::create([
                'user_id' => $user->id,
                'votable_type' => 'App\Models\Post',
                'votable_id' => $post->id,
                'value' => 1,
                'type' => 'upvote',
            ]);
        }
    }

    $response = getJson('/api/v1/stats/engagement');

    $response->assertStatus(200);
    expect($response->json('avg_comments_per_post'))->toBeGreaterThanOrEqual(1);
    expect($response->json('avg_votes_per_post'))->toBeGreaterThanOrEqual(1);
});

test('engagement handles case without posts correctly', function (): void {
    $response = getJson('/api/v1/stats/engagement');

    $response->assertStatus(200);
    expect($response->json('avg_comments_per_post'))->toBe(0);
    expect($response->json('avg_votes_per_post'))->toBe(0);
});

test('trending returns estructura correcta', function (): void {
    $response = getJson('/api/v1/stats/trending');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'trending_posts',
        'trending_tags',
        'trending_users',
    ]);
});

test('general counts today posts correctly', function (): void {
    Post::factory()->count(5)->create(['created_at' => now()]);
    Post::factory()->count(3)->create(['created_at' => now()->subDays(2)]);

    $response = getJson('/api/v1/stats/general');

    $response->assertStatus(200);
    expect($response->json('posts_today'))->toBeGreaterThanOrEqual(5);
    expect($response->json('total_posts'))->toBeGreaterThanOrEqual(8);
});

test('users counts new users by period', function (): void {
    User::factory()->count(2)->create(['created_at' => now()]);
    User::factory()->count(3)->create(['created_at' => now()->subDays(3)]);
    User::factory()->count(4)->create(['created_at' => now()->subDays(10)]);

    $response = getJson('/api/v1/stats/users');

    $response->assertStatus(200);
    expect($response->json('new_users_today'))->toBeGreaterThanOrEqual(2);
    expect($response->json('new_users_week'))->toBeGreaterThanOrEqual(5);
    expect($response->json('new_users_month'))->toBeGreaterThanOrEqual(9);
});
