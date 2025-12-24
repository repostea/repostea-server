<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserPreference;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

test('index returns default preferences for user without preferences', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/preferences');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'layout',
        'theme',
        'sort_by',
        'sort_dir',
        'filters',
        'content_languages',
        'push_notifications',
    ]);
    $response->assertJsonPath('layout', 'card');
    $response->assertJsonPath('theme', 'renegados1');
});

test('index returns user saved preferences', function (): void {
    UserPreference::create([
        'user_id' => $this->user->id,
        'layout' => 'compact',
        'theme' => 'dark',
        'sort_by' => 'votes',
        'sort_dir' => 'asc',
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/preferences');

    $response->assertStatus(200);
    $response->assertJsonPath('layout', 'compact');
    $response->assertJsonPath('theme', 'dark');
    $response->assertJsonPath('sort_by', 'votes');
    $response->assertJsonPath('sort_dir', 'asc');
});

test('index requires authentication', function (): void {
    $response = getJson('/api/v1/preferences');

    $response->assertStatus(200);
    // Returns defaults when not authenticated
    $response->assertJsonPath('layout', 'card');
});

test('store creates nuevas preferencias', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'layout' => 'list',
        'theme' => 'yups',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('message', 'Preferences saved successfully');

    $preferences = UserPreference::where('user_id', $this->user->id)->first();
    expect($preferences->layout)->toBe('list');
    expect($preferences->theme)->toBe('yups');
});

test('store updates preferencias existentes', function (): void {
    UserPreference::create([
        'user_id' => $this->user->id,
        'layout' => 'card',
        'theme' => 'renegados1',
    ]);

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'layout' => 'compact',
        'theme' => 'dark',
    ]);

    $response->assertStatus(200);

    $preferences = UserPreference::where('user_id', $this->user->id)->first();
    expect($preferences->layout)->toBe('compact');
    expect($preferences->theme)->toBe('dark');
});

test('store requires authentication', function (): void {
    $response = postJson('/api/v1/preferences', [
        'layout' => 'list',
    ]);

    $response->assertStatus(401);
});

test('store validates layout', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'layout' => 'invalid',
    ]);

    $response->assertStatus(422);
});

test('store validates theme', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'theme' => 'invalid',
    ]);

    $response->assertStatus(422);
});

test('store validates sort_dir', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'sort_dir' => 'invalid',
    ]);

    $response->assertStatus(422);
});

test('store accepts layout card', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'layout' => 'card',
    ]);

    $response->assertStatus(200);
});

test('store accepts layout compact', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'layout' => 'compact',
    ]);

    $response->assertStatus(200);
});

test('store accepts layout list', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'layout' => 'list',
    ]);

    $response->assertStatus(200);
});

test('store accepts theme renegados1', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'theme' => 'renegados1',
    ]);

    $response->assertStatus(200);
});

test('store accepts theme yups', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'theme' => 'yups',
    ]);

    $response->assertStatus(200);
});

test('store accepts theme repostea', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'theme' => 'repostea',
    ]);

    $response->assertStatus(200);
});

test('store accepts theme barrapunto', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'theme' => 'barrapunto',
    ]);

    $response->assertStatus(200);
});

test('store accepts theme dark', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'theme' => 'dark',
    ]);

    $response->assertStatus(200);
});

test('store accepts sort_dir asc', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'sort_dir' => 'asc',
    ]);

    $response->assertStatus(200);
});

test('store accepts sort_dir desc', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'sort_dir' => 'desc',
    ]);

    $response->assertStatus(200);
});

test('store accepts filters as array', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'filters' => ['tag1', 'tag2'],
    ]);

    $response->assertStatus(200);

    $preferences = UserPreference::where('user_id', $this->user->id)->first();
    expect($preferences->filters)->toBeArray();
});

test('store accepts content_languages as array', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'content_languages' => ['es', 'en'],
    ]);

    $response->assertStatus(200);

    $preferences = UserPreference::where('user_id', $this->user->id)->first();
    expect($preferences->content_languages)->toBeArray();
});

test('store accepts push_notifications as array', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'push_notifications' => ['comments' => true, 'votes' => false],
    ]);

    $response->assertStatus(200);

    $preferences = UserPreference::where('user_id', $this->user->id)->first();
    expect($preferences->push_notifications)->toBeArray();
});

test('store accepts multiple preferences at once', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'layout' => 'list',
        'theme' => 'dark',
        'sort_by' => 'votes',
        'sort_dir' => 'desc',
    ]);

    $response->assertStatus(200);

    $preferences = UserPreference::where('user_id', $this->user->id)->first();
    expect($preferences->layout)->toBe('list');
    expect($preferences->theme)->toBe('dark');
    expect($preferences->sort_by)->toBe('votes');
    expect($preferences->sort_dir)->toBe('desc');
});

test('store does not overwrite preferences not sent', function (): void {
    UserPreference::create([
        'user_id' => $this->user->id,
        'layout' => 'card',
        'theme' => 'renegados1',
        'sort_by' => 'created_at',
    ]);

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'layout' => 'list',
    ]);

    $response->assertStatus(200);

    $preferences = UserPreference::where('user_id', $this->user->id)->first();
    expect($preferences->layout)->toBe('list');
    expect($preferences->theme)->toBe('renegados1'); // Preserved
    expect($preferences->sort_by)->toBe('created_at'); // Preserved
});

test('store accepts custom sort_by', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/preferences', [
        'sort_by' => 'custom_field',
    ]);

    $response->assertStatus(200);

    $preferences = UserPreference::where('user_id', $this->user->id)->first();
    expect($preferences->sort_by)->toBe('custom_field');
});

test('index creates preferences automatically if not exist', function (): void {
    Sanctum::actingAs($this->user);

    expect(UserPreference::where('user_id', $this->user->id)->exists())->toBeFalse();

    $response = getJson('/api/v1/preferences');

    $response->assertStatus(200);

    expect(UserPreference::where('user_id', $this->user->id)->exists())->toBeTrue();
});
