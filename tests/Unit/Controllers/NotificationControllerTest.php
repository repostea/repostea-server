<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Models\User;
use App\Notifications\TestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }

    #[Test]
    public function it_returns_empty_notifications_for_new_user(): void
    {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJson([
                'data' => [],
                'unread_count' => 0,
            ]);
    }

    #[Test]
    public function it_returns_user_notifications(): void
    {
        // Create notifications
        $this->user->notify(new TestNotification('Test Title', 'Test Body', '/test'));
        $this->user->notify(new TestNotification('Test Title 2', 'Test Body 2', '/test2'));

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'type', 'title', 'body', 'read', 'read_at', 'created_at', 'data'],
                ],
                'unread_count',
                'meta',
            ])
            ->assertJsonCount(2, 'data')
            ->assertJson([
                'unread_count' => 2,
            ]);
    }

    #[Test]
    public function it_filters_unread_notifications(): void
    {
        // Create notifications
        $this->user->notify(new TestNotification('Test 1', 'Body 1'));
        $this->user->notify(new TestNotification('Test 2', 'Body 2'));

        // Mark first as read
        $this->user->notifications()->first()->markAsRead();

        $response = $this->getJson('/api/v1/notifications?filter=unread');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJson([
                'unread_count' => 1,
            ]);
    }

    #[Test]
    public function it_filters_read_notifications(): void
    {
        // Create notifications
        $this->user->notify(new TestNotification('Test 1', 'Body 1'));
        $this->user->notify(new TestNotification('Test 2', 'Body 2'));

        // Mark first as read
        $this->user->notifications()->first()->markAsRead();

        $response = $this->getJson('/api/v1/notifications?filter=read');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_marks_notification_as_read(): void
    {
        $this->user->notify(new TestNotification('Test', 'Body'));
        $notification = $this->user->notifications()->first();

        $this->assertNull($notification->read_at);

        $response = $this->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJson([
                'message' => __('messages.notifications.marked_as_read'),
                'unread_count' => 0,
            ]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    #[Test]
    public function it_marks_all_notifications_as_read(): void
    {
        // Create multiple notifications
        for ($i = 0; $i < 5; $i++) {
            $this->user->notify(new TestNotification("Test {$i}", "Body {$i}"));
        }

        $this->assertEquals(5, $this->user->unreadNotifications()->count());

        $response = $this->postJson('/api/v1/notifications/read-all');

        $response->assertOk()
            ->assertJson([
                'message' => __('messages.notifications.all_marked_as_read'),
                'unread_count' => 0,
            ]);

        $this->assertEquals(0, $this->user->unreadNotifications()->count());
    }

    #[Test]
    public function it_deletes_notification(): void
    {
        $this->user->notify(new TestNotification('Test', 'Body'));
        $notification = $this->user->notifications()->first();

        $this->assertEquals(1, $this->user->notifications()->count());

        $response = $this->deleteJson("/api/v1/notifications/{$notification->id}");

        $response->assertOk()
            ->assertJson([
                'message' => __('messages.notifications.deleted'),
            ]);

        $this->assertEquals(0, $this->user->notifications()->count());
    }

    #[Test]
    public function it_returns_404_for_non_existent_notification(): void
    {
        $fakeId = 'fake-notification-id';

        $response = $this->postJson("/api/v1/notifications/{$fakeId}/read");

        $response->assertNotFound()
            ->assertJson([
                'message' => __('messages.notifications.not_found'),
            ]);
    }

    #[Test]
    public function it_returns_404_when_deleting_non_existent_notification(): void
    {
        $fakeId = 'fake-notification-id';

        $response = $this->deleteJson("/api/v1/notifications/{$fakeId}");

        $response->assertNotFound()
            ->assertJson([
                'message' => __('messages.notifications.not_found'),
            ]);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        // Create unauthenticated request
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/notifications');

        $response->assertUnauthorized();
    }

    #[Test]
    public function it_paginates_notifications(): void
    {
        // Create many notifications
        for ($i = 0; $i < 25; $i++) {
            $this->user->notify(new TestNotification("Test {$i}", "Body {$i}"));
        }

        $response = $this->getJson('/api/v1/notifications?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.last_page', 3);
    }

    #[Test]
    public function it_only_returns_own_notifications(): void
    {
        $otherUser = User::factory()->create();

        // Create notification for other user
        $otherUser->notify(new TestNotification('Other User', 'Body'));

        // Create notification for current user
        $this->user->notify(new TestNotification('Current User', 'Body'));

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Current User');
    }
}
