<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\DuplicateContentDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class DuplicateContentDetectorTest extends TestCase
{
    use RefreshDatabase;

    private DuplicateContentDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new DuplicateContentDetector();

        Config::set('rate_limits.suspicious_patterns.duplicate_content_similarity_threshold', 0.9);
        Config::set('rate_limits.suspicious_patterns.rapid_fire_requests', 10);
        Config::set('rate_limits.suspicious_patterns.rapid_fire_window_seconds', 10);
    }

    public function test_it_detects_duplicate_posts_by_title(): void
    {
        $user = User::factory()->create();

        // Create original post
        Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'This is a unique title about Laravel',
            'content' => 'Some content here',
            'created_at' => now()->subHour(),
        ]);

        // Check duplicate with identical title
        $result = $this->detector->checkDuplicatePost(
            $user->id,
            'This is a unique title about Laravel',
            'Some content here',
        );

        $this->assertTrue($result['is_duplicate']);
        $this->assertGreaterThanOrEqual(0.9, $result['similarity']);
        $this->assertInstanceOf(Post::class, $result['duplicate_post']);
    }

    public function test_it_does_not_detect_different_posts(): void
    {
        $user = User::factory()->create();

        Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'First post about PHP',
            'created_at' => now()->subHour(),
        ]);

        $result = $this->detector->checkDuplicatePost(
            $user->id,
            'Completely different topic about JavaScript',
        );

        $this->assertFalse($result['is_duplicate']);
        $this->assertLessThan(0.9, $result['similarity']);
    }

    public function test_it_considers_time_window_for_duplicate_posts(): void
    {
        $user = User::factory()->create();

        // Create old post (outside 24h window)
        Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Old duplicate post',
            'created_at' => now()->subHours(25),
        ]);

        $result = $this->detector->checkDuplicatePost(
            $user->id,
            'Old duplicate post',
            null,
            24,
        );

        $this->assertFalse($result['is_duplicate']);
    }

    public function test_it_detects_duplicate_comments(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        Comment::factory()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'content' => 'This is my comment about the topic',
            'created_at' => now()->subMinutes(30),
        ]);

        $result = $this->detector->checkDuplicateComment(
            $user->id,
            'This is my comment about the topic',
        );

        $this->assertTrue($result['is_duplicate']);
        $this->assertGreaterThanOrEqual(0.9, $result['similarity']);
        $this->assertInstanceOf(Comment::class, $result['duplicate_comment']);
    }

    public function test_it_does_not_detect_different_comments(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        Comment::factory()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'content' => 'First comment',
            'created_at' => now()->subMinutes(30),
        ]);

        $result = $this->detector->checkDuplicateComment(
            $user->id,
            'Completely different comment text',
        );

        $this->assertFalse($result['is_duplicate']);
    }

    public function test_it_handles_empty_content(): void
    {
        $user = User::factory()->create();

        $result = $this->detector->checkDuplicatePost($user->id, '', '');

        $this->assertFalse($result['is_duplicate']);
        $this->assertEquals(0, $result['similarity']);
    }

    public function test_it_detects_rapid_fire_posting(): void
    {
        $user = User::factory()->create();

        // Create 11 posts in last 5 seconds
        for ($i = 0; $i < 11; $i++) {
            Post::factory()->create([
                'user_id' => $user->id,
                'created_at' => now()->subSeconds(5),
            ]);
        }

        $result = $this->detector->checkRapidFire($user->id, 'post');

        $this->assertTrue($result['is_rapid_fire']);
        $this->assertGreaterThanOrEqual(10, $result['count']);
    }

    public function test_it_does_not_detect_normal_posting_rate(): void
    {
        $user = User::factory()->create();

        // Create 3 posts (below threshold)
        for ($i = 0; $i < 3; $i++) {
            Post::factory()->create([
                'user_id' => $user->id,
                'created_at' => now()->subSeconds(5),
            ]);
        }

        $result = $this->detector->checkRapidFire($user->id, 'post');

        $this->assertFalse($result['is_rapid_fire']);
    }

    public function test_it_detects_rapid_fire_commenting(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        // Create 11 comments in last 5 seconds
        for ($i = 0; $i < 11; $i++) {
            Comment::factory()->create([
                'user_id' => $user->id,
                'post_id' => $post->id,
                'created_at' => now()->subSeconds(5),
            ]);
        }

        $result = $this->detector->checkRapidFire($user->id, 'comment');

        $this->assertTrue($result['is_rapid_fire']);
        $this->assertGreaterThanOrEqual(10, $result['count']);
    }

    public function test_it_calculates_spam_score_for_new_account(): void
    {
        $user = User::factory()->create([
            'created_at' => now()->subHours(12), // Less than 1 day old
        ]);

        $result = $this->detector->getSpamScore($user->id);

        $this->assertIsInt($result['score']);
        $this->assertGreaterThan(0, $result['score']);
        $this->assertTrue(
            str_contains(implode('', $result['reasons']), 'new account') ||
            str_contains(implode('', $result['reasons']), 'Very new account'),
        );
    }

    public function test_it_calculates_spam_score_for_rapid_fire(): void
    {
        $user = User::factory()->create([
            'created_at' => now()->subMonths(1),
        ]);

        // Create rapid-fire posts
        for ($i = 0; $i < 11; $i++) {
            Post::factory()->create([
                'user_id' => $user->id,
                'created_at' => now()->subSeconds(5),
            ]);
        }

        $result = $this->detector->getSpamScore($user->id);

        $this->assertGreaterThanOrEqual(30, $result['score']);
        $this->assertContains($result['risk_level'], ['critical', 'high', 'medium']);
    }

    public function test_it_returns_risk_levels_correctly(): void
    {
        $user = User::factory()->create(['created_at' => now()->subMonths(1)]);

        $result = $this->detector->getSpamScore($user->id);

        $this->assertIsString($result['risk_level']);
        $this->assertContains($result['risk_level'], ['minimal', 'low', 'medium', 'high', 'critical']);
    }

    public function test_it_normalizes_strings_for_comparison(): void
    {
        $user = User::factory()->create();

        Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Hello World',
            'created_at' => now()->subHour(),
        ]);

        // Check with different case and spacing
        $result = $this->detector->checkDuplicatePost(
            $user->id,
            '  HELLO   WORLD  ',
        );

        $this->assertTrue($result['is_duplicate']);
    }

    public function test_it_weighs_title_and_content_correctly(): void
    {
        $user = User::factory()->create();

        Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Same Title',
            'content' => 'Original content here',
            'created_at' => now()->subHour(),
        ]);

        // Same title, same content should have high similarity
        $result = $this->detector->checkDuplicatePost(
            $user->id,
            'Same Title',
            'Original content here',
        );

        // Should detect as duplicate with weighted calculation
        $this->assertTrue($result['is_duplicate']);
        $this->assertGreaterThanOrEqual(0.9, $result['similarity']);
    }

    public function test_it_returns_no_duplicate_for_user_with_no_posts(): void
    {
        $user = User::factory()->create();

        $result = $this->detector->checkDuplicatePost($user->id, 'New post');

        $this->assertFalse($result['is_duplicate']);
        $this->assertEquals(0, $result['similarity']);
        $this->assertNull($result['duplicate_post']);
    }

    public function test_it_returns_no_duplicate_for_user_with_no_comments(): void
    {
        $user = User::factory()->create();

        $result = $this->detector->checkDuplicateComment($user->id, 'New comment');

        $this->assertFalse($result['is_duplicate']);
        $this->assertEquals(0, $result['similarity']);
        $this->assertNull($result['duplicate_comment']);
    }
}
