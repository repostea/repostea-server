<?php

declare(strict_types=1);

use App\Models\TransparencyStat;

use function Pest\Laravel\getJson;

test('index returns transparency data', function (): void {
    $response = getJson('/api/v1/transparency');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'stats' => [
                'posts',
                'users',
                'comments',
                'aggregated_sources',
            ],
            'moderation' => [
                'reports' => [
                    'total',
                    'processed',
                    'pending',
                ],
                'avg_response_hours',
                'actions' => [
                    'removed',
                    'warnings',
                    'suspended',
                    'appeals',
                ],
            ],
            'report_types',
        ],
    ]);
});

test('index does not require authentication', function (): void {
    $response = getJson('/api/v1/transparency');

    $response->assertStatus(200);
});

test('index returns default statistics when no data', function (): void {
    $response = getJson('/api/v1/transparency');

    $response->assertStatus(200);
    $response->assertJsonPath('data.stats.posts', 0);
    $response->assertJsonPath('data.stats.users', 0);
    $response->assertJsonPath('data.stats.comments', 0);
    $response->assertJsonPath('data.moderation.reports.total', 0);
});

test('index returns statistics when they exist', function (): void {
    TransparencyStat::create([
        'total_posts' => 100,
        'total_users' => 50,
        'total_comments' => 200,
        'total_aggregated_sources' => 5,
        'reports_total' => 10,
        'reports_processed' => 8,
        'reports_pending' => 2,
        'avg_response_hours' => 24.5,
        'content_removed' => 3,
        'warnings_issued' => 5,
        'users_suspended' => 1,
        'appeals_total' => 2,
        'report_types' => ['spam' => 5, 'harassment' => 3, 'other' => 2],
        'calculated_at' => now(),
    ]);

    $response = getJson('/api/v1/transparency');

    $response->assertStatus(200);
    $response->assertJsonPath('data.stats.posts', 100);
    $response->assertJsonPath('data.stats.users', 50);
    $response->assertJsonPath('data.stats.comments', 200);
    $response->assertJsonPath('data.stats.aggregated_sources', 5);
    $response->assertJsonPath('data.moderation.reports.total', 10);
    $response->assertJsonPath('data.moderation.reports.processed', 8);
    $response->assertJsonPath('data.moderation.reports.pending', 2);
    $response->assertJsonPath('data.moderation.avg_response_hours', 24.5);
    $response->assertJsonPath('data.moderation.actions.removed', 3);
    $response->assertJsonPath('data.moderation.actions.warnings', 5);
    $response->assertJsonPath('data.moderation.actions.suspended', 1);
    $response->assertJsonPath('data.moderation.actions.appeals', 2);
});

test('index includes calculated_at when statistics exist', function (): void {
    TransparencyStat::create([
        'total_posts' => 100,
        'total_users' => 50,
        'total_comments' => 200,
        'total_aggregated_sources' => 5,
        'reports_total' => 10,
        'reports_processed' => 8,
        'reports_pending' => 2,
        'avg_response_hours' => 24.5,
        'content_removed' => 3,
        'warnings_issued' => 5,
        'users_suspended' => 1,
        'appeals_total' => 2,
        'calculated_at' => now(),
    ]);

    $response = getJson('/api/v1/transparency');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'calculated_at',
        ],
    ]);
});

test('index formatea report_types correctamente', function (): void {
    TransparencyStat::create([
        'total_posts' => 100,
        'total_users' => 50,
        'total_comments' => 200,
        'total_aggregated_sources' => 5,
        'reports_total' => 10,
        'reports_processed' => 8,
        'reports_pending' => 2,
        'avg_response_hours' => 24.5,
        'content_removed' => 3,
        'warnings_issued' => 5,
        'users_suspended' => 1,
        'appeals_total' => 2,
        'report_types' => ['spam' => 5, 'harassment' => 3],
        'calculated_at' => now(),
    ]);

    $response = getJson('/api/v1/transparency');

    $response->assertStatus(200);
    $reportTypes = $response->json('data.report_types');
    expect($reportTypes)->toBeArray();
    expect(count($reportTypes))->toBe(2);
    expect($reportTypes[0])->toHaveKey('type');
    expect($reportTypes[0])->toHaveKey('count');
});

test('index returns latest statistic when multiple exist', function (): void {
    TransparencyStat::create([
        'total_posts' => 50,
        'total_users' => 25,
        'total_comments' => 100,
        'total_aggregated_sources' => 3,
        'reports_total' => 5,
        'reports_processed' => 4,
        'reports_pending' => 1,
        'avg_response_hours' => 12.0,
        'content_removed' => 1,
        'warnings_issued' => 2,
        'users_suspended' => 0,
        'appeals_total' => 1,
        'calculated_at' => now()->subDay(),
    ]);

    TransparencyStat::create([
        'total_posts' => 100,
        'total_users' => 50,
        'total_comments' => 200,
        'total_aggregated_sources' => 5,
        'reports_total' => 10,
        'reports_processed' => 8,
        'reports_pending' => 2,
        'avg_response_hours' => 24.5,
        'content_removed' => 3,
        'warnings_issued' => 5,
        'users_suspended' => 1,
        'appeals_total' => 2,
        'calculated_at' => now(),
    ]);

    $response = getJson('/api/v1/transparency');

    $response->assertStatus(200);
    // Should return the latest one (100 posts, not 50)
    $response->assertJsonPath('data.stats.posts', 100);
});

test('index returns empty report_types when no types', function (): void {
    TransparencyStat::create([
        'total_posts' => 100,
        'total_users' => 50,
        'total_comments' => 200,
        'total_aggregated_sources' => 5,
        'reports_total' => 0,
        'reports_processed' => 0,
        'reports_pending' => 0,
        'avg_response_hours' => 0,
        'content_removed' => 0,
        'warnings_issued' => 0,
        'users_suspended' => 0,
        'appeals_total' => 0,
        'calculated_at' => now(),
    ]);

    $response = getJson('/api/v1/transparency');

    $response->assertStatus(200);
    $reportTypes = $response->json('data.report_types');
    expect($reportTypes)->toBeArray();
    expect(count($reportTypes))->toBe(0);
});

test('index handles null report_types correctly', function (): void {
    TransparencyStat::create([
        'total_posts' => 100,
        'total_users' => 50,
        'total_comments' => 200,
        'total_aggregated_sources' => 5,
        'reports_total' => 0,
        'reports_processed' => 0,
        'reports_pending' => 0,
        'avg_response_hours' => 0,
        'content_removed' => 0,
        'warnings_issued' => 0,
        'users_suspended' => 0,
        'appeals_total' => 0,
        'report_types' => null,
        'calculated_at' => now(),
    ]);

    $response = getJson('/api/v1/transparency');

    $response->assertStatus(200);
    $reportTypes = $response->json('data.report_types');
    expect($reportTypes)->toBeArray();
});
