<?php

declare(strict_types=1);

use App\Models\KarmaLevel;
use App\Models\Sub;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function (): void {
    if (KarmaLevel::count() === 0) {
        KarmaLevel::create(['id' => 1, 'name' => 'Novice', 'required_karma' => 0, 'badge' => '🌱']);
        KarmaLevel::create(['id' => 2, 'name' => 'Apprentice', 'required_karma' => 200, 'badge' => '🔍']);
        KarmaLevel::create(['id' => 3, 'name' => 'Contributor', 'required_karma' => 1000, 'badge' => '🌟']);
        KarmaLevel::create(['id' => 4, 'name' => 'Expert', 'required_karma' => 4000, 'badge' => '🏆']);
        KarmaLevel::create(['id' => 5, 'name' => 'Mentor', 'required_karma' => 16000, 'badge' => '👑']);
        KarmaLevel::create(['id' => 6, 'name' => 'Sage', 'required_karma' => 40000, 'badge' => '🔮']);
        KarmaLevel::create(['id' => 7, 'name' => 'Legend', 'required_karma' => 100000, 'badge' => '⭐']);
    }
});

test('user with account less than 30 days CAN create subcommunity if adequate level', function (): void {
    // Account age restriction was removed from the implementation
    // Users only need the appropriate karma level to create subs
    $user = User::factory()->create([
        'karma_points' => 1000,
        'highest_level_id' => 3, // Contributor
        'created_at' => now()->subDays(15), // Only 15 days
    ]);

    Sanctum::actingAs($user);

    $response = postJson('/api/v1/subs', [
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'description' => 'A test subcommunity',
    ]);

    $response->assertStatus(201);
});

test('user without contributor level cannot create subcommunity', function (): void {
    $user = User::factory()->create([
        'karma_points' => 500,
        'highest_level_id' => 2, // Apprentice
    ]);

    DB::table('users')
        ->where('id', $user->id)
        ->update(['created_at' => now()->subDays(40)]);

    $user = User::find($user->id);

    Sanctum::actingAs($user);

    $response = postJson('/api/v1/subs', [
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'description' => 'A test subcommunity',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['karma']);
});

test('contributor user can create 1 subcommunity', function (): void {
    $user = User::factory()->create([
        'karma_points' => 1000,
        'created_at' => now()->subDays(40),
        'highest_level_id' => 3, // Contributor
    ]);

    Sanctum::actingAs($user);

    $response = postJson('/api/v1/subs', [
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'description' => 'A test subcommunity',
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.name', 'test-sub');
    $response->assertJsonPath('data.created_by', $user->id);

    $sub = Sub::where('name', 'test-sub')->first();
    expect(Sub::count())->toBe(1);
    expect($sub->subscribers()->count())->toBe(1);
});

test('contributor user cannot create more than 1 subcommunity', function (): void {
    $user = User::factory()->create([
        'karma_points' => 1000,
        'created_at' => now()->subDays(40),
        'highest_level_id' => 3, // Contributor
    ]);

    Sub::create([
        'name' => 'first-sub',
        'display_name' => 'First Sub',
        'created_by' => $user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    Sanctum::actingAs($user);

    $response = postJson('/api/v1/subs', [
        'name' => 'second-sub',
        'display_name' => 'Second Sub',
        'description' => 'Attempt to create second subcommunity',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['limit']);
});

test('expert user can create subcommunities without limit', function (): void {
    $user = User::factory()->create([
        'karma_points' => 5000,
        'created_at' => now()->subDays(40),
        'highest_level_id' => 4, // Expert
    ]);

    Sub::create([
        'name' => 'first-sub',
        'display_name' => 'First Sub',
        'created_by' => $user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    Sanctum::actingAs($user);

    // Try to create second subcommunity (should work)
    $response = postJson('/api/v1/subs', [
        'name' => 'second-sub',
        'display_name' => 'Second Sub',
        'description' => 'Second subcommunity from expert',
        'icon' => '🎨',
        'color' => '#EC4899',
    ]);

    $response->assertStatus(201);
    expect(Sub::where('created_by', $user->id)->count())->toBe(2);
});

test('required fields validation', function (): void {
    $user = User::factory()->create([
        'karma_points' => 1000,
        'created_at' => now()->subDays(40),
        'highest_level_id' => 3, // Contributor
    ]);

    Sanctum::actingAs($user);

    $response = postJson('/api/v1/subs', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name', 'display_name']);
});

test('unique name validation', function (): void {
    $user = User::factory()->create([
        'karma_points' => 5000,
        'created_at' => now()->subDays(40),
        'highest_level_id' => 4, // Expert
    ]);

    Sub::create([
        'name' => 'existing-sub',
        'display_name' => 'Existing Sub',
        'created_by' => $user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    Sanctum::actingAs($user);

    $response = postJson('/api/v1/subs', [
        'name' => 'existing-sub',
        'display_name' => 'Another Sub',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

test('hexadecimal color format validation', function (): void {
    $user = User::factory()->create([
        'karma_points' => 1000,
        'created_at' => now()->subDays(40),
        'highest_level_id' => 3, // Contributor
    ]);

    Sanctum::actingAs($user);

    $response = postJson('/api/v1/subs', [
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'color' => 'invalid-color',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['color']);
});

test('creator is automatically subscribed when creating subcommunity', function (): void {
    $user = User::factory()->create([
        'karma_points' => 1000,
        'created_at' => now()->subDays(40),
        'highest_level_id' => 3, // Contributor
    ]);

    Sanctum::actingAs($user);

    $response = postJson('/api/v1/subs', [
        'name' => 'auto-subscribe',
        'display_name' => 'Auto Subscribe Sub',
    ]);

    $response->assertStatus(201);

    $sub = Sub::where('name', 'auto-subscribe')->first();
    expect($sub->subscribers()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('expert user can create up to 3 subcommunities', function (): void {
    $user = User::factory()->create([
        'karma_points' => 5000,
        'created_at' => now()->subDays(40),
        'highest_level_id' => 4, // Expert
    ]);

    Sub::create(['name' => 'sub1', 'display_name' => 'Sub 1', 'created_by' => $user->id, 'icon' => '💻', 'color' => '#3B82F6']);
    Sub::create(['name' => 'sub2', 'display_name' => 'Sub 2', 'created_by' => $user->id, 'icon' => '🎨', 'color' => '#EC4899']);

    Sanctum::actingAs($user);

    // Create third subcommunity (should work)
    $response = postJson('/api/v1/subs', [
        'name' => 'sub3',
        'display_name' => 'Sub 3',
        'icon' => '🚀',
        'color' => '#10B981',
    ]);

    $response->assertStatus(201);
    expect(Sub::where('created_by', $user->id)->count())->toBe(3);
});

test('expert user cannot create more than 3 subcommunities', function (): void {
    $user = User::factory()->create([
        'karma_points' => 5000,
        'created_at' => now()->subDays(40),
        'highest_level_id' => 4, // Expert
    ]);

    Sub::create(['name' => 'sub1', 'display_name' => 'Sub 1', 'created_by' => $user->id, 'icon' => '💻', 'color' => '#3B82F6']);
    Sub::create(['name' => 'sub2', 'display_name' => 'Sub 2', 'created_by' => $user->id, 'icon' => '🎨', 'color' => '#EC4899']);
    Sub::create(['name' => 'sub3', 'display_name' => 'Sub 3', 'created_by' => $user->id, 'icon' => '🚀', 'color' => '#10B981']);

    Sanctum::actingAs($user);

    // Try to create fourth subcommunity (should fail)
    $response = postJson('/api/v1/subs', [
        'name' => 'sub4',
        'display_name' => 'Sub 4',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['limit']);
});

test('mentor user can create up to 5 subcommunities', function (): void {
    $user = User::factory()->create([
        'karma_points' => 17000,
        'created_at' => now()->subDays(40),
        'highest_level_id' => 5, // Mentor
    ]);

    for ($i = 1; $i <= 4; $i++) {
        Sub::create([
            'name' => "sub{$i}",
            'display_name' => "Sub {$i}",
            'created_by' => $user->id,
            'icon' => '💻',
            'color' => '#3B82F6',
        ]);
    }

    Sanctum::actingAs($user);

    // Create fifth subcommunity (should work)
    $response = postJson('/api/v1/subs', [
        'name' => 'sub5',
        'display_name' => 'Sub 5',
        'icon' => '🚀',
        'color' => '#10B981',
    ]);

    $response->assertStatus(201);
    expect(Sub::where('created_by', $user->id)->count())->toBe(5);
});

test('sage user can create unlimited subcommunities', function (): void {
    $user = User::factory()->create([
        'karma_points' => 45000,
        'created_at' => now()->subDays(40),
        'highest_level_id' => 6, // Sage
    ]);

    for ($i = 1; $i <= 10; $i++) {
        Sub::create([
            'name' => "sub{$i}",
            'display_name' => "Sub {$i}",
            'created_by' => $user->id,
            'icon' => '💻',
            'color' => '#3B82F6',
        ]);
    }

    Sanctum::actingAs($user);

    // Create subcommunity number 11 (should work)
    $response = postJson('/api/v1/subs', [
        'name' => 'sub11',
        'display_name' => 'Sub 11',
        'icon' => '🚀',
        'color' => '#10B981',
    ]);

    $response->assertStatus(201);
    expect(Sub::where('created_by', $user->id)->count())->toBe(11);
});
