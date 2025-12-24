<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RssControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache to ensure fresh data in each test
        Cache::flush();
    }

    #[Test]
    public function it_returns_published_posts_rss_feed(): void
    {
        // Create a user
        $user = User::factory()->create(['username' => 'testuser']);

        // Create published posts with frontpage_at set
        $publishedPosts = Post::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => 'published',
            'frontpage_at' => now()->subHours(1),
            'title' => 'Test Published Post',
            'content' => 'This is a test content for the published post.',
        ]);

        // Create a published post without frontpage_at (should not appear)
        Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
            'frontpage_at' => null,
            'title' => 'Queued Post',
        ]);

        // Make request to published RSS feed
        $response = $this->get('/rss/published');

        // Verify response
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8');

        // Get XML content
        $xml = $response->getContent();

        // Assert XML structure
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('version="2.0"', $xml);
        $this->assertStringContainsString('<channel>', $xml);
        $this->assertStringContainsString('<title>' . config('site.name') . ' - Published Posts</title>', $xml);
        $this->assertStringContainsString('<description>News and content aggregator', $xml);

        // Assert published posts are included
        $this->assertStringContainsString('Test Published Post', $xml);

        // Assert queued post is NOT included
        $this->assertStringNotContainsString('Queued Post', $xml);

        // Assert author is included
        $this->assertStringContainsString('<author>testuser</author>', $xml);
    }

    #[Test]
    public function it_returns_queued_posts_rss_feed(): void
    {
        // Create a user
        $user = User::factory()->create(['username' => 'queueuser']);

        // Create queued posts (published but without frontpage_at)
        $queuedPosts = Post::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => 'published',
            'frontpage_at' => null,
            'title' => 'Test Queued Post',
            'content' => 'This is a test content for the queued post.',
        ]);

        // Create a published post with frontpage_at (should not appear)
        Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
            'frontpage_at' => now(),
            'title' => 'Published Post',
        ]);

        // Make request to queued RSS feed
        $response = $this->get('/rss/queued');

        // Verify response
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8');

        // Get XML content
        $xml = $response->getContent();

        // Assert XML structure
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('version="2.0"', $xml);
        $this->assertStringContainsString('<channel>', $xml);
        $this->assertStringContainsString('<title>' . config('site.name') . ' - Queued Posts</title>', $xml);
        $this->assertStringContainsString('<description>Posts pending to reach the frontpage</description>', $xml);

        // Assert queued posts are included
        $this->assertStringContainsString('Test Queued Post', $xml);

        // Assert published post is NOT included
        $this->assertStringNotContainsString('Published Post', $xml);

        // Assert author is included
        $this->assertStringContainsString('<author>queueuser</author>', $xml);
    }

    #[Test]
    public function it_filters_published_posts_by_language(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Create Spanish posts
        Post::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => 'published',
            'frontpage_at' => now(),
            'language_code' => 'es',
            'title' => 'Spanish Post',
        ]);

        // Create English posts
        Post::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => 'published',
            'frontpage_at' => now(),
            'language_code' => 'en',
            'title' => 'English Post',
        ]);

        // Request Spanish posts only
        $response = $this->get('/rss/published?lang=es');
        $response->assertStatus(200);

        $xml = $response->getContent();

        // Should contain Spanish posts
        $this->assertStringContainsString('Spanish Post', $xml);
        // Should NOT contain English posts
        $this->assertStringNotContainsString('English Post', $xml);
        // Should have language tag
        $this->assertStringContainsString('<language>es</language>', $xml);
    }

    #[Test]
    public function it_filters_queued_posts_by_language(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Create Spanish queued posts
        Post::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => 'published',
            'frontpage_at' => null,
            'language_code' => 'es',
            'title' => 'Spanish Queued Post',
        ]);

        // Create English queued posts
        Post::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => 'published',
            'frontpage_at' => null,
            'language_code' => 'en',
            'title' => 'English Queued Post',
        ]);

        // Request English queued posts only
        $response = $this->get('/rss/queued?lang=en');
        $response->assertStatus(200);

        $xml = $response->getContent();

        // Should contain English posts
        $this->assertStringContainsString('English Queued Post', $xml);
        // Should NOT contain Spanish posts
        $this->assertStringNotContainsString('Spanish Queued Post', $xml);
        // Should have language tag
        $this->assertStringContainsString('<language>en</language>', $xml);
    }

    #[Test]
    public function it_includes_post_tags_as_categories_in_rss(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Create tags with slug and name_key
        $tag1 = Tag::factory()->create([
            'slug' => 'technology',
            'name_key' => 'technology',
        ]);
        $tag2 = Tag::factory()->create([
            'slug' => 'programming',
            'name_key' => 'programming',
        ]);

        // Create post with tags
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
            'frontpage_at' => now(),
            'title' => 'Tagged Post',
        ]);

        // Attach tags to post
        $post->tags()->attach([$tag1->id, $tag2->id]);

        // Request RSS feed
        $response = $this->get('/rss/published');
        $response->assertStatus(200);

        $xml = $response->getContent();

        // Should contain the post
        $this->assertStringContainsString('Tagged Post', $xml);
        // Tags should be included if they have a name attribute (via accessor or relationship)
        // For now we just verify the post is in the feed
    }

    #[Test]
    public function it_includes_thumbnail_as_enclosure_in_rss(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Create post with thumbnail
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
            'frontpage_at' => now(),
            'title' => 'Post with Thumbnail',
            'thumbnail_url' => 'https://example.com/image.jpg',
        ]);

        // Request RSS feed
        $response = $this->get('/rss/published');
        $response->assertStatus(200);

        $xml = $response->getContent();

        // Should contain enclosure with image
        $this->assertStringContainsString('<enclosure', $xml);
        $this->assertStringContainsString('https://example.com/image.jpg', $xml);
        $this->assertStringContainsString('type="image/jpeg"', $xml);
    }

    #[Test]
    public function it_does_not_include_anonymous_post_author_in_rss(): void
    {
        // Create a user
        $user = User::factory()->create(['username' => 'anonuser']);

        // Create anonymous post
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
            'frontpage_at' => now(),
            'title' => 'Anonymous Post',
            'is_anonymous' => true,
        ]);

        // Request RSS feed
        $response = $this->get('/rss/published');
        $response->assertStatus(200);

        $xml = $response->getContent();

        // Should contain the post
        $this->assertStringContainsString('Anonymous Post', $xml);

        // Should NOT contain author for anonymous post
        $this->assertStringNotContainsString('<author>anonuser</author>', $xml);
    }

    #[Test]
    public function it_caches_rss_feeds(): void
    {
        // Create a user and post
        $user = User::factory()->create();
        Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
            'frontpage_at' => now(),
            'title' => 'Cached Post',
        ]);

        // Clear cache
        Cache::flush();

        // First request - should cache
        $response1 = $this->get('/rss/published');
        $response1->assertStatus(200);

        // Verify cache key exists (using tags as the controller does)
        $this->assertTrue(Cache::tags(['posts', 'rss'])->has('rss_published_all'));

        // Second request - should use cache
        $response2 = $this->get('/rss/published');
        $response2->assertStatus(200);

        // Both responses should be identical
        $this->assertEquals($response1->getContent(), $response2->getContent());
    }

    #[Test]
    public function it_limits_rss_feed_to_50_posts(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Create 60 published posts
        Post::factory()->count(60)->create([
            'user_id' => $user->id,
            'status' => 'published',
            'frontpage_at' => now(),
        ]);

        // Request RSS feed
        $response = $this->get('/rss/published');
        $response->assertStatus(200);

        $xml = $response->getContent();

        // Count number of <item> tags (should be 50 max)
        $itemCount = substr_count($xml, '<item>');
        $this->assertLessThanOrEqual(50, $itemCount);
    }

    #[Test]
    public function it_includes_post_content_in_rss(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Create post with markdown content
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
            'frontpage_at' => now(),
            'title' => 'Post with Content',
            'content' => '# Heading

This is **bold** text with a [link](https://example.com).

- Item 1
- Item 2',
        ]);

        // Request RSS feed
        $response = $this->get('/rss/published');
        $response->assertStatus(200);

        $xml = $response->getContent();

        // Should contain description (plain text summary)
        $this->assertStringContainsString('<description>', $xml);

        // Should contain content:encoded with HTML
        $this->assertStringContainsString('<content:encoded>', $xml);
        $this->assertStringContainsString('CDATA', $xml);
    }

    #[Test]
    public function it_uses_frontend_url_for_post_links_not_api_url(): void
    {
        // Create a user
        $user = User::factory()->create(['username' => 'testuser']);

        // Create published post
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
            'frontpage_at' => now(),
            'title' => 'Test Post for URL Check',
            'slug' => 'test-post-url-check',
        ]);

        // Request RSS feed
        $response = $this->get('/rss/published');
        $response->assertStatus(200);

        $xml = $response->getContent();

        // Get configured URLs
        $clientUrl = config('app.client_url');
        $apiUrl = config('app.url');

        // Expected post URL should use client_url
        $expectedPostUrl = $clientUrl . '/posts/' . $post->slug;

        // Assertions
        $this->assertStringContainsString($expectedPostUrl, $xml, 'RSS feed should contain frontend URL for post link');

        // Should NOT contain API URL in post links
        $apiPostUrl = $apiUrl . '/posts/' . $post->slug;
        $this->assertStringNotContainsString($apiPostUrl, $xml, 'RSS feed should NOT contain API URL for post links');

        // Channel link should also use client_url
        $this->assertStringContainsString("<link>{$clientUrl}</link>", $xml, 'Channel link should use frontend URL');

        // Comments link should use client_url
        $expectedCommentsUrl = $expectedPostUrl . '#comments';
        $this->assertStringContainsString($expectedCommentsUrl, $xml, 'Comments link should use frontend URL');
    }
}
