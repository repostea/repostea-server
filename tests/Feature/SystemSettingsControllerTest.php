<?php

declare(strict_types=1);

use App\Models\SystemSetting;

use function Pest\Laravel\getJson;

test('index returns system settings', function (): void {
    $response = getJson('/api/v1/system/settings');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'registration_mode',
        'guest_access',
        'email_verification',
    ]);
});

test('index returns default values when no settings exist', function (): void {
    SystemSetting::query()->delete();

    $response = getJson('/api/v1/system/settings');

    $response->assertStatus(200);
    $response->assertJsonPath('registration_mode', 'invite_only');
    $response->assertJsonPath('guest_access', 'enabled');
    $response->assertJsonPath('email_verification', 'optional');
});

test('index returns custom registration_mode value', function (): void {
    SystemSetting::set('registration_mode', 'open');

    $response = getJson('/api/v1/system/settings');

    $response->assertStatus(200);
    $response->assertJsonPath('registration_mode', 'open');
});

test('index returns custom guest_access value', function (): void {
    SystemSetting::set('guest_access', 'disabled');

    $response = getJson('/api/v1/system/settings');

    $response->assertStatus(200);
    $response->assertJsonPath('guest_access', 'disabled');
});

test('index returns custom email_verification value', function (): void {
    SystemSetting::set('email_verification', 'required');

    $response = getJson('/api/v1/system/settings');

    $response->assertStatus(200);
    $response->assertJsonPath('email_verification', 'required');
});

test('index does not require authentication', function (): void {
    $response = getJson('/api/v1/system/settings');

    $response->assertStatus(200);
});

test('index returns multiple custom configurations', function (): void {
    SystemSetting::set('registration_mode', 'closed');
    SystemSetting::set('guest_access', 'read_only');
    SystemSetting::set('email_verification', 'required');

    $response = getJson('/api/v1/system/settings');

    $response->assertStatus(200);
    $response->assertJsonPath('registration_mode', 'closed');
    $response->assertJsonPath('guest_access', 'read_only');
    $response->assertJsonPath('email_verification', 'required');
});
