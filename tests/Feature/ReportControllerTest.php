<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

test('store creates post report correctly', function (): void {
    $targetUser = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $targetUser->id]);

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/reports', [
        'reportable_type' => 'post',
        'reportable_id' => $post->id,
        'reason' => 'spam',
        'description' => 'This is spam content',
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'report' => ['id', 'reported_by', 'reported_user_id', 'reportable_type', 'reportable_id', 'reason', 'status'],
    ]);
    $response->assertJsonPath('report.reason', 'spam');
    $response->assertJsonPath('report.status', 'pending');

    expect(Report::where('reported_by', $this->user->id)->count())->toBe(1);
});

test('store creates comment report correctly', function (): void {
    $targetUser = User::factory()->create();
    $post = Post::factory()->create();
    $comment = Comment::factory()->create(['user_id' => $targetUser->id, 'post_id' => $post->id]);

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/reports', [
        'reportable_type' => 'comment',
        'reportable_id' => $comment->id,
        'reason' => 'harassment',
        'description' => 'Harassment comment',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('report.reason', 'harassment');
    $response->assertJsonPath('report.reported_user_id', $targetUser->id);

    expect(Report::where('reported_by', $this->user->id)->count())->toBe(1);
});

test('store creates user report correctly', function (): void {
    $targetUser = User::factory()->create();

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/reports', [
        'reportable_type' => 'user',
        'reportable_id' => $targetUser->id,
        'reason' => 'hate_speech',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('report.reason', 'hate_speech');
    $response->assertJsonPath('report.reported_user_id', $targetUser->id);
});

test('store validates reportable types', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/reports', [
        'reportable_type' => 'invalid_type',
        'reportable_id' => 1,
        'reason' => 'spam',
    ]);

    $response->assertStatus(422);
});

test('store validates report reasons', function (): void {
    $post = Post::factory()->create();

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/reports', [
        'reportable_type' => 'post',
        'reportable_id' => $post->id,
        'reason' => 'invalid_reason',
    ]);

    $response->assertStatus(422);
});

test('store returns error if content does not exist', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/reports', [
        'reportable_type' => 'post',
        'reportable_id' => 99999,
        'reason' => 'spam',
    ]);

    $response->assertStatus(404);
    $response->assertJsonPath('error', 'Content not found');
});

test('store prevents duplicate reports within 30 days', function (): void {
    $post = Post::factory()->create();

    // Create existing report
    Report::create([
        'reported_by' => $this->user->id,
        'reported_user_id' => $post->user_id,
        'reportable_type' => Post::class,
        'reportable_id' => $post->id,
        'reason' => 'spam',
        'status' => 'pending',
        'created_at' => now()->subDays(10),
    ]);

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/reports', [
        'reportable_type' => 'post',
        'reportable_id' => $post->id,
        'reason' => 'spam',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error', 'You have already reported this content');
});

test('store allows reporting same content after 30 days', function (): void {
    $post = Post::factory()->create();

    // Create old report (more than 30 days ago)
    $oldReport = Report::create([
        'reported_by' => $this->user->id,
        'reported_user_id' => $post->user_id,
        'reportable_type' => Post::class,
        'reportable_id' => $post->id,
        'reason' => 'spam',
        'status' => 'pending',
    ]);

    // Manually update timestamp to be older than 30 days
    $oldReport->created_at = now()->subDays(31);
    $oldReport->save();

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/reports', [
        'reportable_type' => 'post',
        'reportable_id' => $post->id,
        'reason' => 'spam',
    ]);

    $response->assertStatus(201);
});

test('store accepts optional description', function (): void {
    $post = Post::factory()->create();

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/reports', [
        'reportable_type' => 'post',
        'reportable_id' => $post->id,
        'reason' => 'other',
    ]);

    $response->assertStatus(201);
});

test('store limits description to 1000 characters', function (): void {
    $post = Post::factory()->create();

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/reports', [
        'reportable_type' => 'post',
        'reportable_id' => $post->id,
        'reason' => 'other',
        'description' => str_repeat('a', 1001),
    ]);

    $response->assertStatus(422);
});

test('index returns user reports', function (): void {
    $post1 = Post::factory()->create();
    $post2 = Post::factory()->create();

    // Create reports by this user
    Report::create([
        'reported_by' => $this->user->id,
        'reported_user_id' => $post1->user_id,
        'reportable_type' => Post::class,
        'reportable_id' => $post1->id,
        'reason' => 'spam',
        'status' => 'pending',
    ]);

    Report::create([
        'reported_by' => $this->user->id,
        'reported_user_id' => $post2->user_id,
        'reportable_type' => Post::class,
        'reportable_id' => $post2->id,
        'reason' => 'harassment',
        'status' => 'pending',
    ]);

    // Create report by another user (should not be returned)
    $otherUser = User::factory()->create();
    Report::create([
        'reported_by' => $otherUser->id,
        'reported_user_id' => $post1->user_id,
        'reportable_type' => Post::class,
        'reportable_id' => $post1->id,
        'reason' => 'spam',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/reports');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'reported_by', 'reason', 'status'],
        ],
    ]);

    expect(count($response->json('data')))->toBe(2);
});

test('index orders reports by date descending', function (): void {
    $posts = Post::factory()->count(3)->create();

    // Create reports with delays to ensure different timestamps
    Report::create([
        'reported_by' => $this->user->id,
        'reported_user_id' => $posts[0]->user_id,
        'reportable_type' => Post::class,
        'reportable_id' => $posts[0]->id,
        'reason' => 'spam',
        'status' => 'pending',
    ]);

    sleep(1); // Wait 1 second to ensure different timestamp

    Report::create([
        'reported_by' => $this->user->id,
        'reported_user_id' => $posts[1]->user_id,
        'reportable_type' => Post::class,
        'reportable_id' => $posts[1]->id,
        'reason' => 'harassment',
        'status' => 'pending',
    ]);

    sleep(1); // Wait 1 second to ensure different timestamp

    $latestReport = Report::create([
        'reported_by' => $this->user->id,
        'reported_user_id' => $posts[2]->user_id,
        'reportable_type' => Post::class,
        'reportable_id' => $posts[2]->id,
        'reason' => 'inappropriate',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/reports');

    $data = $response->json('data');
    // Most recent report should be first
    expect($data[0]['id'])->toBe($latestReport->id);
    expect($data[0]['reason'])->toBe('inappropriate');
});

test('index pagina resultados', function (): void {
    $posts = Post::factory()->count(25)->create();

    // Create 25 reports
    foreach ($posts as $post) {
        Report::create([
            'reported_by' => $this->user->id,
            'reported_user_id' => $post->user_id,
            'reportable_type' => Post::class,
            'reportable_id' => $post->id,
            'reason' => 'spam',
            'status' => 'pending',
        ]);
    }

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/reports');

    $response->assertStatus(200);
    expect(count($response->json('data')))->toBe(20); // First page with 20 items
});

test('show returns specific report', function (): void {
    $post = Post::factory()->create();

    $report = Report::create([
        'reported_by' => $this->user->id,
        'reported_user_id' => $post->user_id,
        'reportable_type' => Post::class,
        'reportable_id' => $post->id,
        'reason' => 'spam',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson("/api/v1/reports/{$report->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('id', $report->id);
    $response->assertJsonPath('reason', 'spam');
});

test('show does not allow viewing other user reports if not moderator', function (): void {
    $otherUser = User::factory()->create();
    $post = Post::factory()->create();

    $report = Report::create([
        'reported_by' => $otherUser->id,
        'reported_user_id' => $post->user_id,
        'reportable_type' => Post::class,
        'reportable_id' => $post->id,
        'reason' => 'spam',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson("/api/v1/reports/{$report->id}");

    $response->assertStatus(403);
    $response->assertJsonPath('error', 'Unauthorized');
});

test('show allows moderator to view any report', function (): void {
    $moderator = User::factory()->create();

    // Create moderator role and attach to user
    $moderatorRole = Role::firstOrCreate(
        ['slug' => 'moderator'],
        ['name' => 'Moderator', 'display_name' => 'Moderator'],
    );
    $moderator->roles()->attach($moderatorRole->id);

    $otherUser = User::factory()->create();
    $post = Post::factory()->create();

    $report = Report::create([
        'reported_by' => $otherUser->id,
        'reported_user_id' => $post->user_id,
        'reportable_type' => Post::class,
        'reportable_id' => $post->id,
        'reason' => 'spam',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($moderator);

    $response = getJson("/api/v1/reports/{$report->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('id', $report->id);
});

test('destroy deletes report correctly', function (): void {
    $post = Post::factory()->create();

    $report = Report::create([
        'reported_by' => $this->user->id,
        'reported_user_id' => $post->user_id,
        'reportable_type' => Post::class,
        'reportable_id' => $post->id,
        'reason' => 'spam',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/reports/{$report->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('message', 'Report deleted successfully');

    expect(Report::find($report->id))->toBeNull();
});

test('destroy does not allow deleting other user reports', function (): void {
    $otherUser = User::factory()->create();
    $post = Post::factory()->create();

    $report = Report::create([
        'reported_by' => $otherUser->id,
        'reported_user_id' => $post->user_id,
        'reportable_type' => Post::class,
        'reportable_id' => $post->id,
        'reason' => 'spam',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/reports/{$report->id}");

    $response->assertStatus(403);
    $response->assertJsonPath('error', 'You can only delete your own reports');
});

test('destroy does not allow deleting resolved reports', function (): void {
    $post = Post::factory()->create();

    $report = Report::create([
        'reported_by' => $this->user->id,
        'reported_user_id' => $post->user_id,
        'reportable_type' => Post::class,
        'reportable_id' => $post->id,
        'reason' => 'spam',
        'status' => 'resolved',
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/reports/{$report->id}");

    $response->assertStatus(422);
    $response->assertJsonPath('error', 'Cannot delete a report that has already been reviewed');
});

test('destroy does not allow deleting dismissed reports', function (): void {
    $post = Post::factory()->create();

    $report = Report::create([
        'reported_by' => $this->user->id,
        'reported_user_id' => $post->user_id,
        'reportable_type' => Post::class,
        'reportable_id' => $post->id,
        'reason' => 'spam',
        'status' => 'dismissed',
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/reports/{$report->id}");

    $response->assertStatus(422);
    $response->assertJsonPath('error', 'Cannot delete a report that has already been reviewed');
});

test('store accepts all valid reasons', function (): void {
    $validReasons = [
        'spam',
        'harassment',
        'inappropriate',
        'misinformation',
        'hate_speech',
        'violence',
        'illegal_content',
        'copyright',
        'other',
    ];

    Sanctum::actingAs($this->user);

    foreach ($validReasons as $reason) {
        $post = Post::factory()->create();

        $response = postJson('/api/v1/reports', [
            'reportable_type' => 'post',
            'reportable_id' => $post->id,
            'reason' => $reason,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('report.reason', $reason);
    }
});
