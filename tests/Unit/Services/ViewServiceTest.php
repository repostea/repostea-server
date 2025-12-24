<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Post;
use App\Models\User;
use App\Services\ViewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ViewServiceTest extends TestCase
{
    use RefreshDatabase;

    private ViewService $viewService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->viewService = app(ViewService::class);
    }

    #[Test]
    public function it_registers_a_view_successfully(): void
    {
        // Arrange
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $ip = '127.0.0.1';
        $userAgent = 'Mozilla/5.0 Test Browser';

        // Assert that post_views table is empty
        $this->assertDatabaseCount('post_views', 0);
        $initialViewCount = $post->views;

        // Act
        $result = $this->viewService->registerView($post, $ip, $user->id, $userAgent);

        // Assert
        $this->assertTrue($result);

        // Check database record was created with correct column names
        $this->assertDatabaseHas('post_views', [
            'post_id' => $post->id,
            'ip_address' => $ip, // CRITICAL: Verify correct column name
            'user_id' => $user->id,
            'user_agent' => $userAgent,
        ]);

        // Check post view count was incremented
        $post->refresh();
        $this->assertEquals($initialViewCount + 1, $post->views);
    }

    #[Test]
    public function it_prevents_duplicate_views_from_same_ip(): void
    {
        // Arrange
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $ip = '127.0.0.1';
        $userAgent = 'Mozilla/5.0 Test Browser';

        // Act - Register view twice
        $firstResult = $this->viewService->registerView($post, $ip, $user->id, $userAgent);
        $secondResult = $this->viewService->registerView($post, $ip, $user->id, $userAgent);

        // Assert
        $this->assertTrue($firstResult);
        $this->assertFalse($secondResult); // Second view should be blocked

        // Only one record should exist
        $this->assertDatabaseCount('post_views', 1);
    }

    #[Test]
    public function it_handles_null_user_id(): void
    {
        // Arrange
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $ip = '127.0.0.1';
        $userAgent = 'Mozilla/5.0 Test Browser';

        // Act
        $result = $this->viewService->registerView($post, $ip, null, $userAgent);

        // Assert
        $this->assertTrue($result);

        $this->assertDatabaseHas('post_views', [
            'post_id' => $post->id,
            'ip_address' => $ip,
            'user_id' => null,
            'user_agent' => $userAgent,
        ]);
    }

    #[Test]
    public function it_handles_null_ip_address(): void
    {
        // Arrange
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $userAgent = 'Mozilla/5.0 Test Browser';

        // Act & Assert - null IP should return false as the database requires a non-null IP
        // The service should gracefully handle this case
        $result = $this->viewService->registerView($post, null, $user->id, $userAgent);

        // The result should be false since the view cannot be registered without an IP
        $this->assertFalse($result);
    }

    #[Test]
    public function it_uses_cache_to_prevent_duplicate_views(): void
    {
        // Arrange
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $ip = '127.0.0.1';
        $viewKey = 'post_view_' . $post->id . '_user_' . $user->id;

        // Pre-populate cache to simulate recent view
        Cache::put($viewKey, true, now()->addMinutes(1));

        // Act
        $result = $this->viewService->registerView($post, $ip, $user->id, 'Test Browser');

        // Assert
        $this->assertFalse($result);
        // Note: For authenticated users, view record is created/updated even if cached
        // Cache only prevents multiple updates within 1 minute window
    }

    #[Test]
    public function it_verifies_post_views_table_structure(): void
    {
        // This test ensures the table has the correct column structure
        // to prevent the 'ip' vs 'ip_address' column issue from recurring

        $columns = DB::getSchemaBuilder()->getColumnListing('post_views');

        $this->assertContains('id', $columns);
        $this->assertContains('post_id', $columns);
        $this->assertContains('user_id', $columns);
        $this->assertContains('ip_address', $columns); // CRITICAL: Must be 'ip_address', not 'ip'
        $this->assertContains('user_agent', $columns);
        $this->assertContains('created_at', $columns);
        $this->assertContains('updated_at', $columns);

        // Ensure 'ip' column does NOT exist (common mistake)
        $this->assertNotContains('ip', $columns);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
