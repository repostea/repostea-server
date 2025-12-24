<?php

declare(strict_types=1);

use App\Models\KarmaEvent;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function (): void {
    // Create admin role if it doesn't exist
    $adminRole = Role::firstOrCreate(
        ['slug' => 'admin'],
        ['name' => 'Administrator', 'display_name' => 'Administrator'],
    );

    $this->admin = User::factory()->create();
    $this->admin->roles()->attach($adminRole);

    $this->user = User::factory()->create();
});

test('index returns karma events', function (): void {
    Sanctum::actingAs($this->admin);

    KarmaEvent::factory()->create([
        'name' => 'Double Points',
        'type' => 'tide',
        'multiplier' => 2.0,
        'is_active' => true,
    ]);

    KarmaEvent::factory()->create([
        'name' => 'Triple Points',
        'type' => 'boost',
        'multiplier' => 3.0,
        'is_active' => false,
    ]);

    $response = getJson('/api/v1/admin/karma-events');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'name', 'type', 'multiplier', 'is_active'],
        ],
    ]);

    $events = $response->json('data');
    expect(count($events))->toBe(2);
});

test('index requires admin authentication', function (): void {
    $response = getJson('/api/v1/admin/karma-events');

    $response->assertStatus(401);
});

test('index does not allow access to normal users', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/admin/karma-events');

    $response->assertStatus(403);
});

test('store creates new karma event', function (): void {
    Sanctum::actingAs($this->admin);

    $response = postJson('/api/v1/admin/karma-events', [
        'name' => 'New Year Surge',
        'description' => 'Triple karma for new year',
        'type' => 'surge',
        'multiplier' => 3.0,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(7),
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('message', 'Karma event created successfully.');

    expect(KarmaEvent::where('name', 'New Year Surge')->exists())->toBeTrue();
});

test('store requires admin authentication', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/admin/karma-events', [
        'name' => 'Test Event',
        'description' => 'Test',
        'type' => 'tide',
        'multiplier' => 2.0,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(7),
    ]);

    $response->assertStatus(403);
});

test('store validates name requerido', function (): void {
    Sanctum::actingAs($this->admin);

    $response = postJson('/api/v1/admin/karma-events', [
        'description' => 'Test',
        'type' => 'tide',
        'multiplier' => 2.0,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(7),
    ]);

    $response->assertStatus(422);
});

test('store validates description requerida', function (): void {
    Sanctum::actingAs($this->admin);

    $response = postJson('/api/v1/admin/karma-events', [
        'name' => 'Test',
        'type' => 'tide',
        'multiplier' => 2.0,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(7),
    ]);

    $response->assertStatus(422);
});

test('store validates type requerido', function (): void {
    Sanctum::actingAs($this->admin);

    $response = postJson('/api/v1/admin/karma-events', [
        'name' => 'Test',
        'description' => 'Test',
        'multiplier' => 2.0,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(7),
    ]);

    $response->assertStatus(422);
});

test('store validates multiplier requerido', function (): void {
    Sanctum::actingAs($this->admin);

    $response = postJson('/api/v1/admin/karma-events', [
        'name' => 'Test',
        'description' => 'Test',
        'type' => 'tide',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(7),
    ]);

    $response->assertStatus(422);
});

test('store validates start_at requerido', function (): void {
    Sanctum::actingAs($this->admin);

    $response = postJson('/api/v1/admin/karma-events', [
        'name' => 'Test',
        'description' => 'Test',
        'type' => 'tide',
        'multiplier' => 2.0,
        'end_at' => now()->addDays(7),
    ]);

    $response->assertStatus(422);
});

test('store validates end_at requerido', function (): void {
    Sanctum::actingAs($this->admin);

    $response = postJson('/api/v1/admin/karma-events', [
        'name' => 'Test',
        'description' => 'Test',
        'type' => 'tide',
        'multiplier' => 2.0,
        'start_at' => now()->addDay(),
    ]);

    $response->assertStatus(422);
});

test('store validates type valores permitidos', function (): void {
    Sanctum::actingAs($this->admin);

    $response = postJson('/api/v1/admin/karma-events', [
        'name' => 'Test',
        'description' => 'Test',
        'type' => 'invalid',
        'multiplier' => 2.0,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(7),
    ]);

    $response->assertStatus(422);
});

test('store accepts type tide', function (): void {
    Sanctum::actingAs($this->admin);

    $response = postJson('/api/v1/admin/karma-events', [
        'name' => 'Test',
        'description' => 'Test',
        'type' => 'tide',
        'multiplier' => 2.0,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(7),
    ]);

    $response->assertStatus(201);
});

test('store accepts type boost', function (): void {
    Sanctum::actingAs($this->admin);

    $response = postJson('/api/v1/admin/karma-events', [
        'name' => 'Test',
        'description' => 'Test',
        'type' => 'boost',
        'multiplier' => 2.0,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(7),
    ]);

    $response->assertStatus(201);
});

test('store accepts type surge', function (): void {
    Sanctum::actingAs($this->admin);

    $response = postJson('/api/v1/admin/karma-events', [
        'name' => 'Test',
        'description' => 'Test',
        'type' => 'surge',
        'multiplier' => 2.0,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(7),
    ]);

    $response->assertStatus(201);
});

test('store accepts type wave', function (): void {
    Sanctum::actingAs($this->admin);

    $response = postJson('/api/v1/admin/karma-events', [
        'name' => 'Test',
        'description' => 'Test',
        'type' => 'wave',
        'multiplier' => 2.0,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(7),
    ]);

    $response->assertStatus(201);
});

test('store validates minimum multiplier', function (): void {
    Sanctum::actingAs($this->admin);

    $response = postJson('/api/v1/admin/karma-events', [
        'name' => 'Test',
        'description' => 'Test',
        'type' => 'tide',
        'multiplier' => 0,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(7),
    ]);

    $response->assertStatus(422);
});

test('store validates maximum multiplier', function (): void {
    Sanctum::actingAs($this->admin);

    $response = postJson('/api/v1/admin/karma-events', [
        'name' => 'Test',
        'description' => 'Test',
        'type' => 'tide',
        'multiplier' => 11,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(7),
    ]);

    $response->assertStatus(422);
});

test('store validates end_at posterior a start_at', function (): void {
    Sanctum::actingAs($this->admin);

    $response = postJson('/api/v1/admin/karma-events', [
        'name' => 'Test',
        'description' => 'Test',
        'type' => 'tide',
        'multiplier' => 2.0,
        'start_at' => now()->addDays(7),
        'end_at' => now()->addDay(),
    ]);

    $response->assertStatus(422);
});

test('store validates start_at in the future', function (): void {
    Sanctum::actingAs($this->admin);

    $response = postJson('/api/v1/admin/karma-events', [
        'name' => 'Test',
        'description' => 'Test',
        'type' => 'tide',
        'multiplier' => 2.0,
        'start_at' => now()->subDay(),
        'end_at' => now()->addDay(),
    ]);

    $response->assertStatus(422);
});

test('update updates existing event', function (): void {
    $event = KarmaEvent::factory()->create([
        'name' => 'Old Name',
        'multiplier' => 2.0,
    ]);

    Sanctum::actingAs($this->admin);

    $response = putJson("/api/v1/admin/karma-events/{$event->id}", [
        'name' => 'Updated Name',
        'multiplier' => 3.0,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('message', 'Karma event updated successfully.');

    $event->refresh();
    expect($event->name)->toBe('Updated Name');
    expect($event->multiplier)->toBe(3.0);
});

test('update requires admin authentication', function (): void {
    $event = KarmaEvent::factory()->create();

    Sanctum::actingAs($this->user);

    $response = putJson("/api/v1/admin/karma-events/{$event->id}", [
        'name' => 'Updated',
    ]);

    $response->assertStatus(403);
});

test('update returns 404 for non-existent event', function (): void {
    Sanctum::actingAs($this->admin);

    $response = putJson('/api/v1/admin/karma-events/99999', [
        'name' => 'Updated',
    ]);

    $response->assertStatus(404);
});

test('destroy deletes karma event', function (): void {
    $event = KarmaEvent::factory()->create();

    Sanctum::actingAs($this->admin);

    $response = deleteJson("/api/v1/admin/karma-events/{$event->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('message', 'Karma event deleted successfully.');

    expect(KarmaEvent::find($event->id))->toBeNull();
});

test('destroy requires admin authentication', function (): void {
    $event = KarmaEvent::factory()->create();

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/admin/karma-events/{$event->id}");

    $response->assertStatus(403);
});

test('destroy returns 404 for non-existent event', function (): void {
    Sanctum::actingAs($this->admin);

    $response = deleteJson('/api/v1/admin/karma-events/99999');

    $response->assertStatus(404);
});

test('update allows updating only some fields', function (): void {
    $event = KarmaEvent::factory()->create([
        'name' => 'Original',
        'multiplier' => 2.0,
        'type' => 'tide',
    ]);

    Sanctum::actingAs($this->admin);

    $response = putJson("/api/v1/admin/karma-events/{$event->id}", [
        'multiplier' => 3.0,
    ]);

    $response->assertStatus(200);

    $event->refresh();
    expect($event->name)->toBe('Original');
    expect($event->multiplier)->toBe(3.0);
    expect($event->type)->toBe('tide');
});

test('index includes date information', function (): void {
    Sanctum::actingAs($this->admin);

    KarmaEvent::factory()->create([
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(7),
    ]);

    $response = getJson('/api/v1/admin/karma-events');

    $response->assertStatus(200);
    $events = $response->json('data');
    expect($events[0])->toHaveKey('start_at');
    expect($events[0])->toHaveKey('end_at');
});
