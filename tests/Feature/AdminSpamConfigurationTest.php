<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\SpamSetting;
use App\Models\User;

beforeEach(function (): void {
    // Create roles
    Role::create([
        'name' => 'admin',
        'slug' => 'admin',
        'display_name' => 'Administrator',
        'description' => 'Administrator role for testing',
    ]);

    Role::create([
        'name' => 'moderator',
        'slug' => 'moderator',
        'display_name' => 'Moderator',
        'description' => 'Moderator role for testing',
    ]);
});

test('non-authenticated users cannot access spam configuration', function (): void {
    $response = $this->get(route('admin.spam-configuration'));

    $response->assertRedirect(route('admin.login'));
});

test('non-admin users cannot access spam configuration', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.spam-configuration'));

    $response->assertForbidden();
});

test('admin can view spam configuration page', function (): void {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.spam-configuration'));

    $response->assertOk();
    $response->assertViewIs('admin.spam-configuration');
});

test('moderator can view spam configuration page', function (): void {
    $moderator = User::factory()->moderator()->create();

    $response = $this->actingAs($moderator)->get(route('admin.spam-configuration'));

    $response->assertOk();
    $response->assertViewIs('admin.spam-configuration');
});

test('spam configuration page displays settings grouped by category', function (): void {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.spam-configuration'));

    $response->assertOk();
    $response->assertViewHas('settings', fn ($settings) => $settings->has('Duplicate Detection')
            && $settings->has('Spam Score')
            && $settings->has('Rapid Fire Detection')
            && $settings->has('Automatic Actions'));
});

test('admin can update spam configuration', function (): void {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson(route('admin.spam-configuration.update'), [
        'settings' => [
            ['key' => 'duplicate_detection_enabled', 'value' => '0'],
            ['key' => 'spam_score_threshold', 'value' => '80'],
        ],
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'message' => 'Configuration updated successfully',
    ]);

    expect(SpamSetting::getValue('duplicate_detection_enabled'))->toBeFalse();
    expect(SpamSetting::getValue('spam_score_threshold'))->toBe(80);
});

test('moderator can update spam configuration', function (): void {
    $moderator = User::factory()->moderator()->create();

    $response = $this->actingAs($moderator)->postJson(route('admin.spam-configuration.update'), [
        'settings' => [
            ['key' => 'rapid_fire_enabled', 'value' => '1'],
        ],
    ]);

    $response->assertOk();
    expect(SpamSetting::getValue('rapid_fire_enabled'))->toBeTrue();
});

test('non-admin users cannot update spam configuration', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('admin.spam-configuration.update'), [
        'settings' => [
            ['key' => 'spam_score_threshold', 'value' => '90'],
        ],
    ]);

    $response->assertForbidden();
});

test('spam configuration update validates required fields', function (): void {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson(route('admin.spam-configuration.update'), [
        'settings' => [],
    ]);

    $response->assertStatus(422);
});

test('spam configuration update validates setting structure', function (): void {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->postJson(route('admin.spam-configuration.update'), [
        'settings' => [
            ['key' => 'test_setting'], // Missing value
        ],
    ]);

    $response->assertStatus(422);
});

test('spam settings use cache for getValue', function (): void {
    $admin = User::factory()->admin()->create();

    // First call - should hit database
    $value1 = SpamSetting::getValue('duplicate_detection_enabled');

    // Second call - should use cache
    $value2 = SpamSetting::getValue('duplicate_detection_enabled');

    expect($value1)->toBe($value2);
});

test('spam settings clear cache when setValue is called', function (): void {
    $admin = User::factory()->admin()->create();

    SpamSetting::setValue('spam_score_threshold', 75);

    $value = SpamSetting::getValue('spam_score_threshold');

    expect($value)->toBe(75);
});

test('spam settings can handle boolean values', function (): void {
    SpamSetting::setValue('test_boolean', true);
    expect(SpamSetting::getValue('test_boolean'))->toBeTrue();

    SpamSetting::setValue('test_boolean', false);
    expect(SpamSetting::getValue('test_boolean'))->toBeFalse();
});

test('spam settings can handle integer values', function (): void {
    SpamSetting::setValue('test_integer', 100);
    expect(SpamSetting::getValue('test_integer'))->toBe(100);
});

test('spam settings can handle float values', function (): void {
    SpamSetting::setValue('test_float', 0.85);
    expect(SpamSetting::getValue('test_float'))->toBe(0.85);
});

test('spam settings can handle string values', function (): void {
    SpamSetting::setValue('test_string', 'hello world');
    expect(SpamSetting::getValue('test_string'))->toBe('hello world');
});

test('spam settings getValue returns default when setting does not exist', function (): void {
    $value = SpamSetting::getValue('nonexistent_setting', 'default_value');
    expect($value)->toBe('default_value');
});

test('spam settings creates new setting if it does not exist', function (): void {
    SpamSetting::setValue('new_setting', 'new_value');

    $setting = SpamSetting::where('key', 'new_setting')->first();
    expect($setting)->not->toBeNull();
    expect($setting->value)->toBe('new_value');
});

test('spam settings updates existing setting', function (): void {
    SpamSetting::create([
        'key' => 'existing_setting',
        'value' => 'old_value',
        'type' => 'string',
    ]);

    SpamSetting::setValue('existing_setting', 'new_value');

    $setting = SpamSetting::where('key', 'existing_setting')->first();
    expect($setting->value)->toBe('new_value');
});
