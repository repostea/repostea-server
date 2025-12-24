<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    // Create admin role
    Role::create([
        'name' => 'admin',
        'slug' => 'admin',
        'display_name' => 'Administrator',
        'description' => 'Administrator role for testing',
    ]);

    $this->admin = User::factory()->admin()->create();
    $this->user = User::factory()->create();
});

// Access control tests
test('spam detection requires authentication', function (): void {
    $response = get('/admin/spam-detection');

    $response->assertRedirect('/admin/login');
});

test('spam detection requires admin permissions', function (): void {
    actingAs($this->user);

    $response = get('/admin/spam-detection');

    $response->assertStatus(403);
});

test('admin can access spam detection', function (): void {
    actingAs($this->admin);

    $response = get('/admin/spam-detection');

    $response->assertStatus(200);
    $response->assertViewIs('admin.spam-detection');
});

// View data tests
test('spam detection shows correct statistics', function (): void {
    actingAs($this->admin);

    // Setup cache with test data
    Cache::put('spam_score_users', [1, 2, 3], now()->addMinutes(20));
    Cache::put('spam_score_last_scan', now()->subMinutes(5), now()->addHours(1));

    $response = get('/admin/spam-detection');

    $response->assertStatus(200);
    $response->assertViewHas('stats', fn ($stats) => isset($stats['total_processed'])
            && isset($stats['suspicious_count'], $stats['high_risk_count'], $stats['last_scan']),

    );
});

test('spam detection shows suspicious users', function (): void {
    actingAs($this->admin);

    // Create a suspicious user with activity
    $suspiciousUser = User::factory()->create();
    Post::factory()->create([
        'user_id' => $suspiciousUser->id,
        'created_at' => now(),
    ]);

    // Add spam score to cache
    Cache::put('spam_score_users', [$suspiciousUser->id], now()->addMinutes(20));
    Cache::put("spam_score:{$suspiciousUser->id}", [
        'score' => 75,
        'risk_level' => 'high',
        'reasons' => ['Rapid posting', 'Duplicate content'],
        'is_spam' => true,
    ], now()->addMinutes(15));

    $response = get('/admin/spam-detection');

    $response->assertStatus(200);
    $response->assertViewHas('suspiciousUsers', fn ($users) => count($users) > 0
            && $users[0]['user']->id === $suspiciousUser->id
            && $users[0]['spam_score'] === 75);
});

test('spam detection does not show users below minimum score', function (): void {
    actingAs($this->admin);

    $lowScoreUser = User::factory()->create();

    Cache::put('spam_score_users', [$lowScoreUser->id], now()->addMinutes(20));
    Cache::put("spam_score:{$lowScoreUser->id}", [
        'score' => 30,
        'risk_level' => 'low',
        'reasons' => ['Some activity'],
        'is_spam' => false,
    ], now()->addMinutes(15));

    $response = get('/admin/spam-detection?min_score=50');

    $response->assertStatus(200);
    $response->assertViewHas('suspiciousUsers', fn ($users) => count($users) === 0);
});

test('spam detection orders users by spam score descending', function (): void {
    actingAs($this->admin);

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    Cache::put('spam_score_users', [$user1->id, $user2->id, $user3->id], now()->addMinutes(20));

    Cache::put("spam_score:{$user1->id}", [
        'score' => 60,
        'risk_level' => 'medium',
        'reasons' => ['Reason 1'],
        'is_spam' => true,
    ], now()->addMinutes(15));

    Cache::put("spam_score:{$user2->id}", [
        'score' => 85,
        'risk_level' => 'critical',
        'reasons' => ['Reason 2'],
        'is_spam' => true,
    ], now()->addMinutes(15));

    Cache::put("spam_score:{$user3->id}", [
        'score' => 70,
        'risk_level' => 'high',
        'reasons' => ['Reason 3'],
        'is_spam' => true,
    ], now()->addMinutes(15));

    $response = get('/admin/spam-detection');

    $response->assertStatus(200);
    $response->assertViewHas('suspiciousUsers', function ($users) use ($user2, $user3, $user1) {
        return $users[0]['user']->id === $user2->id // 85
            && $users[1]['user']->id === $user3->id // 70
            && $users[2]['user']->id === $user1->id; // 60
    });
});

// Filter tests
test('spam detection respects minimum score filter', function (): void {
    actingAs($this->admin);

    $response = get('/admin/spam-detection?min_score=70');

    $response->assertStatus(200);
    $response->assertViewHas('minScore', 70);
});

test('spam detection respects hours filter', function (): void {
    actingAs($this->admin);

    $response = get('/admin/spam-detection?hours=48');

    $response->assertStatus(200);
    $response->assertViewHas('hours', 48);
});

test('spam detection uses default filter values', function (): void {
    actingAs($this->admin);

    $response = get('/admin/spam-detection');

    $response->assertStatus(200);
    $response->assertViewHas('minScore', 50);
    $response->assertViewHas('hours', 24);
});

// Activity counting tests
test('spam detection counts recent posts correctly', function (): void {
    actingAs($this->admin);

    $user = User::factory()->create();

    // Create posts within time window
    Post::factory()->count(3)->create([
        'user_id' => $user->id,
        'created_at' => now()->subHours(12),
    ]);

    // Create old post outside window
    Post::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->subDays(5),
    ]);

    Cache::put('spam_score_users', [$user->id], now()->addMinutes(20));
    Cache::put("spam_score:{$user->id}", [
        'score' => 75,
        'risk_level' => 'high',
        'reasons' => ['Test'],
        'is_spam' => true,
    ], now()->addMinutes(15));

    $response = get('/admin/spam-detection?hours=24');

    $response->assertStatus(200);
    $response->assertViewHas('suspiciousUsers', fn ($users) => $users[0]['recent_posts'] === 3);
});

test('spam detection counts recent comments correctly', function (): void {
    actingAs($this->admin);

    $user = User::factory()->create();

    // Create comments within time window
    Comment::factory()->count(5)->create([
        'user_id' => $user->id,
        'created_at' => now()->subHours(6),
    ]);

    // Create old comment outside window
    Comment::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->subDays(3),
    ]);

    Cache::put('spam_score_users', [$user->id], now()->addMinutes(20));
    Cache::put("spam_score:{$user->id}", [
        'score' => 80,
        'risk_level' => 'high',
        'reasons' => ['Test'],
        'is_spam' => true,
    ], now()->addMinutes(15));

    $response = get('/admin/spam-detection?hours=24');

    $response->assertStatus(200);
    $response->assertViewHas('suspiciousUsers', fn ($users) => $users[0]['recent_comments'] === 5);
});

// Edge cases
test('spam detection handles empty cache correctly', function (): void {
    actingAs($this->admin);

    Cache::forget('spam_score_users');

    $response = get('/admin/spam-detection');

    $response->assertStatus(200);
    $response->assertViewHas('suspiciousUsers', fn ($users) => count($users) === 0);
});

test('spam detection ignores deleted users', function (): void {
    actingAs($this->admin);

    $deletedUser = User::factory()->create();
    $deletedUserId = $deletedUser->id;
    $deletedUser->delete();

    Cache::put('spam_score_users', [$deletedUserId], now()->addMinutes(20));
    Cache::put("spam_score:{$deletedUserId}", [
        'score' => 90,
        'risk_level' => 'critical',
        'reasons' => ['Test'],
        'is_spam' => true,
    ], now()->addMinutes(15));

    $response = get('/admin/spam-detection');

    $response->assertStatus(200);
    $response->assertViewHas('suspiciousUsers', fn ($users) => count($users) === 0);
});

test('spam detection calcula high risk count correctamente', function (): void {
    actingAs($this->admin);

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    Cache::put('spam_score_users', [$user1->id, $user2->id, $user3->id], now()->addMinutes(20));

    // High risk (>= 70)
    Cache::put("spam_score:{$user1->id}", [
        'score' => 75,
        'risk_level' => 'high',
        'reasons' => ['Test'],
        'is_spam' => true,
    ], now()->addMinutes(15));

    // High risk (>= 70)
    Cache::put("spam_score:{$user2->id}", [
        'score' => 85,
        'risk_level' => 'critical',
        'reasons' => ['Test'],
        'is_spam' => true,
    ], now()->addMinutes(15));

    // Not high risk
    Cache::put("spam_score:{$user3->id}", [
        'score' => 60,
        'risk_level' => 'medium',
        'reasons' => ['Test'],
        'is_spam' => true,
    ], now()->addMinutes(15));

    $response = get('/admin/spam-detection');

    $response->assertStatus(200);
    $response->assertViewHas('stats', fn ($stats) => $stats['high_risk_count'] === 2);
});
