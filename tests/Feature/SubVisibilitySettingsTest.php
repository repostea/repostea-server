<?php

declare(strict_types=1);

use App\Models\Sub;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

beforeEach(function (): void {
    $this->owner = User::factory()->create([
        'karma_points' => 5000,
        'highest_level_id' => 4,
        'created_at' => now()->subDays(40),
    ]);

    $this->moderator = User::factory()->create();
    $this->member = User::factory()->create();
});

test('show returns public_moderators when hide_moderators is false', function (): void {
    $sub = Sub::create([
        'name' => 'visible-mods',
        'display_name' => 'Visible Mods',
        'created_by' => $this->owner->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'hide_moderators' => false,
    ]);

    // Add moderator
    $sub->moderators()->attach($this->moderator->id, [
        'is_owner' => false,
        'added_by' => $this->owner->id,
    ]);

    $response = getJson("/api/v1/subs/{$sub->name}");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'public_moderators' => [
                '*' => ['id', 'username'],
            ],
        ],
    ]);
    expect(count($response->json('data.public_moderators')))->toBeGreaterThanOrEqual(1);
});

test('show returns empty public_moderators when hide_moderators is true', function (): void {
    $sub = Sub::create([
        'name' => 'hidden-mods',
        'display_name' => 'Hidden Mods',
        'created_by' => $this->owner->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'hide_moderators' => true,
    ]);

    // Add moderator
    $sub->moderators()->attach($this->moderator->id, [
        'is_owner' => false,
        'added_by' => $this->owner->id,
    ]);

    $response = getJson("/api/v1/subs/{$sub->name}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.public_moderators', []);
});

test('show includes owner in public_moderators with is_owner flag', function (): void {
    $sub = Sub::create([
        'name' => 'owner-mod',
        'display_name' => 'Owner Mod',
        'created_by' => $this->owner->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'hide_moderators' => false,
    ]);

    $response = getJson("/api/v1/subs/{$sub->name}");

    $response->assertStatus(200);
    $moderators = $response->json('data.public_moderators');
    expect($moderators)->toBeArray();

    // Find owner in list
    $ownerInList = collect($moderators)->first(fn ($m) => $m['id'] === $this->owner->id);
    expect($ownerInList)->not->toBeNull();
    expect($ownerInList['pivot']['is_owner'])->toBeTrue();
});

test('show limits public_moderators to 5', function (): void {
    $sub = Sub::create([
        'name' => 'many-mods',
        'display_name' => 'Many Mods',
        'created_by' => $this->owner->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'hide_moderators' => false,
    ]);

    // Add 10 moderators
    for ($i = 0; $i < 10; $i++) {
        $mod = User::factory()->create();
        $sub->moderators()->attach($mod->id, [
            'is_owner' => false,
            'added_by' => $this->owner->id,
        ]);
    }

    $response = getJson("/api/v1/subs/{$sub->name}");

    $response->assertStatus(200);
    expect(count($response->json('data.public_moderators')))->toBeLessThanOrEqual(5);
});

test('show returns hide_owner field', function (): void {
    $sub = Sub::create([
        'name' => 'hide-owner-test',
        'display_name' => 'Hide Owner Test',
        'created_by' => $this->owner->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'hide_owner' => true,
    ]);

    $response = getJson("/api/v1/subs/{$sub->name}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.hide_owner', true);
});

test('show returns hide_moderators field', function (): void {
    $sub = Sub::create([
        'name' => 'hide-mods-field',
        'display_name' => 'Hide Mods Field',
        'created_by' => $this->owner->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'hide_moderators' => true,
    ]);

    $response = getJson("/api/v1/subs/{$sub->name}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.hide_moderators', true);
});

test('update allows owner to set hide_owner', function (): void {
    $sub = Sub::create([
        'name' => 'update-hide-owner',
        'display_name' => 'Update Hide Owner',
        'created_by' => $this->owner->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'hide_owner' => false,
    ]);

    Sanctum::actingAs($this->owner);

    $response = putJson("/api/v1/subs/{$sub->id}", [
        'hide_owner' => true,
    ]);

    $response->assertStatus(200);

    $sub->refresh();
    expect($sub->hide_owner)->toBeTrue();
});

test('update allows owner to set hide_moderators', function (): void {
    $sub = Sub::create([
        'name' => 'update-hide-mods',
        'display_name' => 'Update Hide Mods',
        'created_by' => $this->owner->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'hide_moderators' => false,
    ]);

    Sanctum::actingAs($this->owner);

    $response = putJson("/api/v1/subs/{$sub->id}", [
        'hide_moderators' => true,
    ]);

    $response->assertStatus(200);

    $sub->refresh();
    expect($sub->hide_moderators)->toBeTrue();
});

test('update rejects non-owner from changing visibility settings', function (): void {
    $sub = Sub::create([
        'name' => 'owner-only-update',
        'display_name' => 'Owner Only Update',
        'created_by' => $this->owner->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'hide_owner' => false,
    ]);

    Sanctum::actingAs($this->member);

    $response = putJson("/api/v1/subs/{$sub->id}", [
        'hide_owner' => true,
    ]);

    $response->assertStatus(403);
});

test('visibility fields default to false', function (): void {
    $sub = Sub::create([
        'name' => 'default-visibility',
        'display_name' => 'Default Visibility',
        'created_by' => $this->owner->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    // Refresh to get database defaults
    $sub->refresh();

    expect($sub->hide_owner)->toBeFalse();
    expect($sub->hide_moderators)->toBeFalse();
});
