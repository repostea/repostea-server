<?php

declare(strict_types=1);

use App\Models\KarmaHistory;
use App\Models\KarmaLevel;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

test('show returns user karma', function (): void {
    $level = KarmaLevel::factory()->create([
        'name' => 'Beginner',
        'required_karma' => 0,
    ]);

    $user = User::factory()->create([
        'karma_points' => 100,
        'highest_level_id' => $level->id,
    ]);

    $response = getJson("/api/v1/users/{$user->id}/karma");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'user_id',
            'karma_points',
            'level',
            'next_level',
            'recent_history',
        ],
    ]);
    $response->assertJsonPath('data.user_id', $user->id);
    $response->assertJsonPath('data.karma_points', 100);
});

test('show includes user current level', function (): void {
    $level = KarmaLevel::factory()->create([
        'name' => 'Expert',
        'badge' => 'expert_badge',
        'required_karma' => 100,
    ]);

    $user = User::factory()->create([
        'karma_points' => 150,
        'highest_level_id' => $level->id,
    ]);

    $response = getJson("/api/v1/users/{$user->id}/karma");

    $response->assertStatus(200);
    expect($response->json('data.level.name'))->toBe('Expert');
    expect($response->json('data.level.badge'))->toBe('expert_badge');
});

test('show includes next level when exists', function (): void {
    $currentLevel = KarmaLevel::factory()->create([
        'name' => 'Beginner',
        'required_karma' => 0,
    ]);

    $nextLevel = KarmaLevel::factory()->create([
        'name' => 'Intermediate',
        'required_karma' => 200,
        'badge' => 'intermediate_badge',
    ]);

    $user = User::factory()->create([
        'karma_points' => 100,
        'highest_level_id' => $currentLevel->id,
    ]);

    $response = getJson("/api/v1/users/{$user->id}/karma");

    $response->assertStatus(200);
    expect($response->json('data.next_level'))->not->toBeNull();
    expect($response->json('data.next_level.required_karma'))->toBeGreaterThan(100);
    expect($response->json('data.next_level.points_needed'))->toBeGreaterThan(0);
});

test('show includes recent karma history', function (): void {
    $user = User::factory()->create(['karma_points' => 100]);

    KarmaHistory::factory()->create([
        'user_id' => $user->id,
        'amount' => 50,
        'source' => 'post_upvote',
        'description' => 'Received upvote on post',
    ]);

    KarmaHistory::factory()->create([
        'user_id' => $user->id,
        'amount' => 25,
        'source' => 'comment_upvote',
        'description' => 'Received upvote on comment',
    ]);

    $response = getJson("/api/v1/users/{$user->id}/karma");

    $response->assertStatus(200);
    $history = $response->json('data.recent_history');
    expect(count($history))->toBe(2);
});

test('show limita historial a 10 entradas', function (): void {
    $user = User::factory()->create(['karma_points' => 500]);

    for ($i = 0; $i < 15; $i++) {
        KarmaHistory::factory()->create([
            'user_id' => $user->id,
            'amount' => 10,
            'source' => 'test',
        ]);
    }

    $response = getJson("/api/v1/users/{$user->id}/karma");

    $response->assertStatus(200);
    $history = $response->json('data.recent_history');
    expect(count($history))->toBe(10);
});

test('show orders history by date descending', function (): void {
    $user = User::factory()->create(['karma_points' => 100]);

    $oldEntry = KarmaHistory::create([
        'user_id' => $user->id,
        'amount' => 10,
        'source' => 'old',
        'description' => 'Old entry',
        'created_at' => now()->subDays(2),
    ]);

    $newEntry = KarmaHistory::create([
        'user_id' => $user->id,
        'amount' => 20,
        'source' => 'new',
        'description' => 'New entry',
        'created_at' => now(),
    ]);

    $response = getJson("/api/v1/users/{$user->id}/karma");

    $response->assertStatus(200);
    $history = $response->json('data.recent_history');
    expect($history[0]['description'])->toBe('New entry');
    expect($history[1]['description'])->toBe('Old entry');
});

test('levels returns all karma levels', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $response = getJson('/api/v1/karma/levels');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'name', 'required_karma'],
        ],
    ]);
    expect(count($response->json('data')))->toBeGreaterThan(0);
});

test('levels orders by required karma ascending', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $response = getJson('/api/v1/karma/levels');

    $response->assertStatus(200);
    $levels = $response->json('data');

    // Verify ordering is ascending
    for ($i = 1; $i < count($levels); $i++) {
        expect($levels[$i]['required_karma'])->toBeGreaterThanOrEqual($levels[$i - 1]['required_karma']);
    }
});

test('leaderboard returns users ordered by karma', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $response = getJson('/api/v1/karma/leaderboard');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'data',
            'current_page',
            'per_page',
            'total',
            'last_page',
        ],
    ]);

    $users = $response->json('data.data');

    // Verify users are sorted by karma_points descending
    for ($i = 1; $i < count($users); $i++) {
        expect($users[$i]['karma_points'])->toBeLessThanOrEqual($users[$i - 1]['karma_points']);
    }
});

test('leaderboard respects specified limit', function (): void {
    Sanctum::actingAs(User::factory()->create());

    User::factory()->count(15)->create();

    $response = getJson('/api/v1/karma/leaderboard?limit=5');

    $response->assertStatus(200);
    $users = $response->json('data.data');
    expect(count($users))->toBe(5);
});

test('leaderboard uses default limit of 10', function (): void {
    Sanctum::actingAs(User::factory()->create());

    User::factory()->count(15)->create();

    $response = getJson('/api/v1/karma/leaderboard');

    $response->assertStatus(200);
    $users = $response->json('data.data');
    expect(count($users))->toBe(10);
});

test('leaderboard includes pagination info', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $response = getJson('/api/v1/karma/leaderboard?limit=10&page=2');

    $response->assertStatus(200);
    expect($response->json('data.current_page'))->toBe(2);
    expect($response->json('data.per_page'))->toBe(10);
    expect($response->json('data.total'))->toBeGreaterThan(0);
});

test('leaderboard includes user current level', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $level = KarmaLevel::factory()->create([
        'name' => 'Master',
        'badge' => 'master_badge',
    ]);

    $user = User::factory()->create([
        'username' => 'topuser',
        'karma_points' => 1000,
        'highest_level_id' => $level->id,
    ]);

    $response = getJson('/api/v1/karma/leaderboard');

    $response->assertStatus(200);
    $users = $response->json('data.data');
    $topUser = collect($users)->firstWhere('username', 'topuser');
    expect($topUser)->not->toBeNull();
});

test('show returns 404 if user does not exist', function (): void {
    $response = getJson('/api/v1/users/99999/karma');

    $response->assertStatus(404);
});

test('show handles user without current level', function (): void {
    $user = User::factory()->create([
        'karma_points' => 0,
        'highest_level_id' => null,
    ]);

    $response = getJson("/api/v1/users/{$user->id}/karma");

    $response->assertStatus(200);
    expect($response->json('data.level'))->toBeNull();
});

test('show handles user without history', function (): void {
    $user = User::factory()->create(['karma_points' => 0]);

    $response = getJson("/api/v1/users/{$user->id}/karma");

    $response->assertStatus(200);
    $history = $response->json('data.recent_history');
    expect($history)->toBeArray();
    expect(count($history))->toBe(0);
});

test('levels returns empty array if no levels', function (): void {
    Sanctum::actingAs(User::factory()->create());

    KarmaLevel::query()->delete();

    $response = getJson('/api/v1/karma/levels');

    $response->assertStatus(200);
    expect($response->json('data'))->toBeArray();
    expect(count($response->json('data')))->toBe(0);
});
