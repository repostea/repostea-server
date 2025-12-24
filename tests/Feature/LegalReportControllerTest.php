<?php

declare(strict_types=1);

use App\Models\LegalReport;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\postJson;

beforeEach(function (): void {
    // Set dummy Turnstile secret for testing
    Config::set('turnstile.secret_key', 'dummy-secret-key');

    // Mock Turnstile validation
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([
            'success' => true,
            'error-codes' => [],
        ], 200),
    ]);
});

// store tests
test('store does not require authentication', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'spam',
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'This is a spam content report.',
        'good_faith' => true,
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(201);
});

test('store requires type', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'Test description',
        'good_faith' => true,
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['type']);
});

test('store requires content_url', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'spam',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'Test description',
        'good_faith' => true,
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_url']);
});

test('store requires reporter_name', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'spam',
        'content_url' => 'https://example.com/content',
        'reporter_email' => 'john@example.com',
        'description' => 'Test description',
        'good_faith' => true,
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['reporter_name']);
});

test('store requires reporter_email', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'spam',
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'description' => 'Test description',
        'good_faith' => true,
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['reporter_email']);
});

test('store requires description', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'spam',
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'good_faith' => true,
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['description']);
});

test('store requires good_faith', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'spam',
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'Test description',
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['good_faith']);
});

test('store requires cf-turnstile-response', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'spam',
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'Test description',
        'good_faith' => true,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['cf-turnstile-response']);
});

test('store validates type is in allowed list', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'invalid-type',
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'Test description',
        'good_faith' => true,
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['type']);
});

test('store validates content_url is valid URL', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'spam',
        'content_url' => 'not-a-url',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'Test description',
        'good_faith' => true,
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content_url']);
});

test('store validates reporter_email is valid email', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'spam',
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'invalid-email',
        'description' => 'Test description',
        'good_faith' => true,
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['reporter_email']);
});

test('store creates legal report correctly', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'copyright',
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'reporter_organization' => 'ACME Corp',
        'description' => 'This is a copyright infringement.',
        'original_url' => 'https://example.com/original',
        'ownership_proof' => 'I own this content.',
        'good_faith' => true,
        'authorized' => true,
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(201);
    expect(LegalReport::count())->toBe(1);
});

test('store returns reference_number', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'spam',
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'Test description',
        'good_faith' => true,
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'success',
        'message',
        'data' => ['report_id', 'reference_number', 'status'],
    ]);
    expect($response->json('data.reference_number'))->toMatch('/^REP-\d{8}-[A-F0-9]{4}$/');
});

test('store requires authorized for copyright reports', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'copyright',
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'Copyright infringement',
        'good_faith' => true,
        'authorized' => false,
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.authorized', ['You must confirm you are authorized to file this copyright report.']);
});

test('store accepts copyright reports with authorized true', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'copyright',
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'Copyright infringement',
        'good_faith' => true,
        'authorized' => true,
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(201);
});

test('store saves reporter IP', function (): void {
    postJson('/api/v1/legal-reports', [
        'type' => 'spam',
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'Test description',
        'good_faith' => true,
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $report = LegalReport::first();
    expect($report->ip_address)->not()->toBeNull();
});

test('store accepts optional locale', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'spam',
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'Test description',
        'good_faith' => true,
        'locale' => 'es',
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(201);
    $report = LegalReport::first();
    expect($report->locale)->toBe('es');
});

test('store validates locale is in allowed list', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'spam',
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'Test description',
        'good_faith' => true,
        'locale' => 'fr',
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['locale']);
});

test('store detects locale from Accept-Language header', function (): void {
    $response = postJson('/api/v1/legal-reports', [
        'type' => 'spam',
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'Test description',
        'good_faith' => true,
        'cf-turnstile-response' => 'dummy-token',
    ], [
        'Accept-Language' => 'es-ES,es;q=0.9',
    ]);

    $response->assertStatus(201);
    $report = LegalReport::first();
    expect($report->locale)->toBe('es');
});

test('store sets status as pending', function (): void {
    postJson('/api/v1/legal-reports', [
        'type' => 'spam',
        'content_url' => 'https://example.com/content',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'Test description',
        'good_faith' => true,
        'cf-turnstile-response' => 'dummy-token',
    ]);

    $report = LegalReport::first();
    expect($report->status)->toBe('pending');
});

// status tests
test('status requires reference_number', function (): void {
    $response = postJson('/api/v1/legal-reports/status', [
        'email' => 'john@example.com',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['reference_number']);
});

test('status requires email', function (): void {
    $response = postJson('/api/v1/legal-reports/status', [
        'reference_number' => 'REP-20241113-ABCD',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('status validates reference_number format', function (): void {
    $response = postJson('/api/v1/legal-reports/status', [
        'reference_number' => 'invalid-format',
        'email' => 'john@example.com',
    ]);

    $response->assertStatus(400);
    $response->assertJsonPath('message', 'Invalid reference number format.');
});

test('status returns error if report does not exist', function (): void {
    $response = postJson('/api/v1/legal-reports/status', [
        'reference_number' => 'REP-20241113-ABCD',
        'email' => 'john@example.com',
    ]);

    $response->assertStatus(404);
    $response->assertJsonPath('message', 'Report not found or email does not match.');
});

test('status returns error if email does not match', function (): void {
    $report = LegalReport::create([
        'reference_number' => 'REP-20241113-ABCD',
        'type' => 'spam',
        'content_url' => 'https://example.com',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'Test',
        'good_faith' => true,
        'status' => 'pending',
        'ip_address' => '127.0.0.1',
        'locale' => 'en',
    ]);

    $response = postJson('/api/v1/legal-reports/status', [
        'reference_number' => 'REP-20241113-ABCD',
        'email' => 'wrong@example.com',
    ]);

    $response->assertStatus(404);
});

test('status returns report information with correct credentials', function (): void {
    $report = LegalReport::create([
        'reference_number' => 'REP-20241113-ABCD',
        'type' => 'copyright',
        'content_url' => 'https://example.com',
        'reporter_name' => 'John Doe',
        'reporter_email' => 'john@example.com',
        'description' => 'Test',
        'good_faith' => true,
        'status' => 'reviewed',
        'ip_address' => '127.0.0.1',
        'locale' => 'en',
    ]);

    $response = postJson('/api/v1/legal-reports/status', [
        'reference_number' => 'REP-20241113-ABCD',
        'email' => 'john@example.com',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'data' => [
            'reference_number',
            'status',
            'type',
            'submitted_at',
            'reviewed_at',
            'user_response',
            'response_sent_at',
        ],
    ]);
    expect($response->json('data.status'))->toBe('reviewed');
});
