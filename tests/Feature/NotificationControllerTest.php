<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

test('getNotificationPreferences returns notification preferences', function (): void {
    Sanctum::actingAs($this->user);

    // Use the actual endpoint /preferences instead of /push-preferences
    $response = getJson('/api/v1/notifications/preferences');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'notification_preferences',
        'digest_frequency',
        'quiet_hours_enabled',
        'timezone',
    ]);
});

test('updateNotificationPreferences updates preferencias', function (): void {
    Sanctum::actingAs($this->user);

    // Use the actual endpoint /preferences instead of /push-preferences
    $response = putJson('/api/v1/notifications/preferences', [
        'quiet_hours_enabled' => true,
        'quiet_hours_start' => '22:00',
        'quiet_hours_end' => '08:00',
    ]);

    $response->assertStatus(200);
});

test('index returns user notifications', function (): void {
    // Create notifications
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => [
            'type' => 'comment',
            'title' => 'New comment',
            'body' => 'Someone commented on your post',
        ],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => [
            'type' => 'vote',
            'title' => 'New vote',
            'body' => 'Someone voted on your post',
        ],
        'read_at' => now(),
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'type', 'title', 'body', 'read', 'created_at'],
        ],
        'unread_count',
        'meta',
    ]);

    expect(count($response->json('data')))->toBe(2);
});

test('index filters unread notifications', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Unread', 'body' => 'Unread notification'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'vote', 'title' => 'Read', 'body' => 'Read notification'],
        'read_at' => now(),
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications?filter=unread');

    $response->assertStatus(200);
    $data = $response->json('data');

    expect(count($data))->toBe(1);
    expect($data[0]['read'])->toBeFalse();
});

test('index filters read notifications', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Unread', 'body' => 'Unread notification'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'vote', 'title' => 'Read', 'body' => 'Read notification'],
        'read_at' => now(),
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications?filter=read');

    $response->assertStatus(200);
    $data = $response->json('data');

    expect(count($data))->toBe(1);
    expect($data[0]['read'])->toBeTrue();
});

test('index pagina resultados', function (): void {
    for ($i = 0; $i < 25; $i++) {
        DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'data' => ['type' => 'test', 'title' => "Notification {$i}", 'body' => 'Test'],
            'read_at' => null,
        ]);
    }

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications?per_page=10');

    $response->assertStatus(200);
    $data = $response->json('data');

    expect(count($data))->toBe(10);
    expect($response->json('meta.total'))->toBe(25);
});

test('index requires authentication', function (): void {
    $response = getJson('/api/v1/notifications');

    $response->assertStatus(401);
});

test('markAsRead marks notification as read', function (): void {
    $notification = DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Test', 'body' => 'Test notification'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/notifications/{$notification->id}/read");

    $response->assertStatus(200);
    $response->assertJsonPath('message', __('messages.notifications.marked_as_read'));

    $notification->refresh();
    expect($notification->read_at)->not->toBeNull();
});

test('markAsRead returns 404 if notification not found', function (): void {
    Sanctum::actingAs($this->user);

    $fakeId = Str::uuid()->toString();
    $response = postJson("/api/v1/notifications/{$fakeId}/read");

    $response->assertStatus(404);
    $response->assertJsonPath('message', __('messages.notifications.not_found'));
});

test('markAsRead does not allow marking other user notifications', function (): void {
    $otherUser = User::factory()->create();
    $notification = DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $otherUser->id,
        'data' => ['type' => 'comment', 'title' => 'Test', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/notifications/{$notification->id}/read");

    $response->assertStatus(404);
});

test('markAllAsRead marks all notifications as read', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Test 1', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'vote', 'title' => 'Test 2', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/notifications/read-all');

    $response->assertStatus(200);
    $response->assertJsonPath('message', __('messages.notifications.all_marked_as_read'));
    $response->assertJsonPath('unread_count', 0);

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

test('destroy deletes notification', function (): void {
    $notification = DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Test', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/notifications/{$notification->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('message', __('messages.notifications.deleted'));

    expect(DatabaseNotification::find($notification->id))->toBeNull();
});

test('destroy returns 404 if notification not found', function (): void {
    Sanctum::actingAs($this->user);

    $fakeId = Str::uuid()->toString();
    $response = deleteJson("/api/v1/notifications/{$fakeId}");

    $response->assertStatus(404);
});

test('destroy does not allow deleting other user notifications', function (): void {
    $otherUser = User::factory()->create();
    $notification = DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $otherUser->id,
        'data' => ['type' => 'comment', 'title' => 'Test', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = deleteJson("/api/v1/notifications/{$notification->id}");

    $response->assertStatus(404);

    expect(DatabaseNotification::find($notification->id))->not->toBeNull();
});

test('index includes unread_count', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Unread 1', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Unread 2', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Read', 'body' => 'Test'],
        'read_at' => now(),
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications');

    $response->assertStatus(200);
    expect($response->json('unread_count'))->toBe(2);
});

test('index orders notifications by date descending', function (): void {
    $old = DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Old', 'body' => 'Old notification'],
        'read_at' => null,
        'created_at' => now()->subDays(2),
    ]);

    $new = DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'New', 'body' => 'New notification'],
        'read_at' => null,
        'created_at' => now(),
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications');

    $data = $response->json('data');
    expect($data[0]['title'])->toBe('New');
    expect($data[1]['title'])->toBe('Old');
});

// Notification Summary Tests (grouped by category)
test('getSummary returns summary grouped by categories', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\PostCommented',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Post Comment', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\CommentReplied',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment_reply', 'title' => 'Comment Reply', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\UserMentioned',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'mention', 'title' => 'Mention', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications/summary');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'summary' => [
            'posts' => ['total', 'unread', 'new'],
            'comments' => ['total', 'unread', 'new'],
            'mentions' => ['total', 'unread', 'new'],
            'achievements' => ['total', 'unread', 'new'],
        ],
        'total_unread',
    ]);

    expect($response->json('summary.posts'))->toBe(['total' => 1, 'unread' => 1, 'new' => 1]);
    expect($response->json('summary.comments'))->toBe(['total' => 1, 'unread' => 1, 'new' => 1]);
    expect($response->json('summary.mentions'))->toBe(['total' => 1, 'unread' => 1, 'new' => 1]);
    expect($response->json('total_unread'))->toBe(3);
});

test('getSummary includes all categories even if empty', function (): void {
    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications/summary');

    $response->assertStatus(200);
    expect($response->json('summary.posts'))->toBe(['total' => 0, 'unread' => 0, 'new' => 0]);
    expect($response->json('summary.comments'))->toBe(['total' => 0, 'unread' => 0, 'new' => 0]);
    expect($response->json('summary.mentions'))->toBe(['total' => 0, 'unread' => 0, 'new' => 0]);
    expect($response->json('summary.achievements'))->toBe(['total' => 0, 'unread' => 0, 'new' => 0]);
    expect($response->json('total_unread'))->toBe(0);
});

test('getSummary correctly counts read and unread notifications', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\PostCommented',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Read Comment', 'body' => 'Test'],
        'read_at' => now(),
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\PostCommented',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Unread Comment', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications/summary');

    expect($response->json('summary.posts'))->toBe(['total' => 2, 'unread' => 1, 'new' => 1]);
    expect($response->json('total_unread'))->toBe(1);
});

test('index can filter by posts category', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\PostCommented',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Post Comment', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\CommentReplied',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment_reply', 'title' => 'Comment Reply', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications?category=posts');

    $response->assertStatus(200);
    expect(count($response->json('data')))->toBe(1);
    expect($response->json('data.0.type'))->toBe('comment');
});

test('index can filter by comments category', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\PostCommented',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Post Comment', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\CommentReplied',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment_reply', 'title' => 'Comment Reply', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications?category=comments');

    $response->assertStatus(200);
    expect(count($response->json('data')))->toBe(1);
    expect($response->json('data.0.type'))->toBe('comment_reply');
});

test('index can filter by mentions category', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\UserMentioned',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'mention', 'title' => 'Mention', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\PostCommented',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Comment', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications?category=mentions');

    $response->assertStatus(200);
    expect(count($response->json('data')))->toBe(1);
    expect($response->json('data.0.type'))->toBe('mention');
});

test('index can filter by achievements category', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\AchievementUnlocked',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'achievement', 'title' => 'Achievement', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\KarmaLevelUp',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'karma_level_up', 'title' => 'Level Up', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\PostCommented',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Comment', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications?category=achievements');

    $response->assertStatus(200);
    expect(count($response->json('data')))->toBe(2);
});

test('index can combine category and read status filters', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\PostCommented',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Read Comment', 'body' => 'Test'],
        'read_at' => now(),
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\PostCommented',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Unread Comment', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications?category=posts&filter=unread');
    expect(count($response->json('data')))->toBe(1);

    $response = getJson('/api/v1/notifications?category=posts&filter=read');
    expect(count($response->json('data')))->toBe(1);
});

test('getSummary requires authentication', function (): void {
    $response = getJson('/api/v1/notifications/summary');
    $response->assertStatus(401);
});

// Agora Notification Tests
test('getSummary categoriza AgoraUserMentioned como mentions', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\AgoraUserMentioned',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'agora_mention', 'title' => 'Agora Mention', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\UserMentioned',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'mention', 'title' => 'Post Mention', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications/summary');

    $response->assertStatus(200);
    expect($response->json('summary.mentions.total'))->toBe(2);
    expect($response->json('summary.mentions.unread'))->toBe(2);
});

test('getSummary categoriza AgoraMessageReplied como comments', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\AgoraMessageReplied',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'agora_reply', 'title' => 'Agora Reply', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\CommentReplied',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment_reply', 'title' => 'Comment Reply', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications/summary');

    $response->assertStatus(200);
    expect($response->json('summary.comments.total'))->toBe(2);
    expect($response->json('summary.comments.unread'))->toBe(2);
});

test('index filters AgoraUserMentioned in mentions category', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\AgoraUserMentioned',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'agora_mention', 'title' => 'Agora Mention', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\UserMentioned',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'mention', 'title' => 'Post Mention', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\PostCommented',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Comment', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications?category=mentions');

    $response->assertStatus(200);
    expect(count($response->json('data')))->toBe(2);

    $types = collect($response->json('data'))->pluck('type')->toArray();
    expect($types)->toContain('mention');
});

test('index filters AgoraMessageReplied in comments category', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\AgoraMessageReplied',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'agora_reply', 'title' => 'Agora Reply', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\CommentReplied',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment_reply', 'title' => 'Comment Reply', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\PostCommented',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'comment', 'title' => 'Post Comment', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications?category=comments');

    $response->assertStatus(200);
    expect(count($response->json('data')))->toBe(2);

    $types = collect($response->json('data'))->pluck('type')->toArray();
    expect($types)->each->toBe('comment_reply');
});

test('index transforma AgoraUserMentioned a tipo mention', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\AgoraUserMentioned',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'agora_mention', 'title' => 'Agora Mention', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications');

    $response->assertStatus(200);
    expect($response->json('data.0.type'))->toBe('mention');
});

test('index transforma AgoraMessageReplied a tipo comment_reply', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\AgoraMessageReplied',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'agora_reply', 'title' => 'Agora Reply', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications');

    $response->assertStatus(200);
    expect($response->json('data.0.type'))->toBe('comment_reply');
});

test('system category excludes Agora notifications', function (): void {
    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\AgoraUserMentioned',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'agora_mention', 'title' => 'Agora Mention', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\AgoraMessageReplied',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'agora_reply', 'title' => 'Agora Reply', 'body' => 'Test'],
        'read_at' => null,
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\SystemNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['type' => 'system', 'title' => 'System', 'body' => 'Test'],
        'read_at' => null,
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/notifications?category=system');

    $response->assertStatus(200);
    expect(count($response->json('data')))->toBe(1);
    expect($response->json('data.0.title'))->toBe('System');
});
