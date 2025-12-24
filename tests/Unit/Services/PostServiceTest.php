<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Jobs\DeliverMultiActorPost;
use App\Jobs\DeliverPostDelete;
use App\Models\ActivityPubPostSettings;
use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class PostServiceTest extends TestCase
{
    use RefreshDatabase;

    private PostService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PostService::class);

        // Clear cache before each test to ensure fresh results
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function test_it_filters_posts_for_frontpage(): void
    {
        // Create posts with votes above threshold and frontpage_at set
        $highVotePosts = Post::factory()->count(3)->create([
            'votes_count' => 5,
            'frontpage_at' => now(),
        ]);

        // Create posts with votes below threshold (no frontpage_at)
        $lowVotePosts = Post::factory()->count(3)->create([
            'votes_count' => 1,
            'frontpage_at' => null,
        ]);

        // Create request with frontpage section
        $request = new Request(['section' => 'frontpage']);

        // Get frontpage posts
        $frontpagePosts = $this->service->getPosts($request);

        // Assert
        $this->assertCount(3, $frontpagePosts);

        foreach ($highVotePosts as $post) {
            $this->assertContains($post->id, $frontpagePosts->pluck('id')->toArray());
        }

        foreach ($lowVotePosts as $post) {
            $this->assertNotContains($post->id, $frontpagePosts->pluck('id')->toArray());
        }
    }

    public function test_it_filters_posts_for_pending(): void
    {
        // Create posts that have reached frontpage (frontpage_at is set)
        $frontpagePosts = Post::factory()->count(3)->create([
            'votes_count' => 5,
            'frontpage_at' => now(),
        ]);

        // Create posts that are still pending (frontpage_at is null)
        $pendingPostsCreated = Post::factory()->count(3)->create([
            'votes_count' => 1,
            'frontpage_at' => null,
        ]);

        // Create request with pending section
        $request = new Request(['section' => 'pending']);

        // Get pending posts
        $pendingPosts = $this->service->getPosts($request);

        // Assert - pending shows posts without frontpage_at
        $this->assertCount(3, $pendingPosts);

        foreach ($pendingPostsCreated as $post) {
            $this->assertContains($post->id, $pendingPosts->pluck('id')->toArray());
        }

        foreach ($frontpagePosts as $post) {
            $this->assertNotContains($post->id, $pendingPosts->pluck('id')->toArray());
        }
    }

    public function test_it_sorts_posts_correctly(): void
    {
        // Create posts with different timestamps, votes, comments, views
        Post::factory()->create([
            'title' => 'Newest',
            'created_at' => now(),
            'votes_count' => 5,
            'comment_count' => 3,
            'views' => 20,
        ]);

        Post::factory()->create([
            'title' => 'Most votes',
            'created_at' => now()->subDay(),
            'votes_count' => 10,
            'comment_count' => 2,
            'views' => 15,
        ]);

        Post::factory()->create([
            'title' => 'Most comments',
            'created_at' => now()->subDays(2),
            'votes_count' => 7,
            'comment_count' => 8,
            'views' => 25,
        ]);

        Post::factory()->create([
            'title' => 'Most views',
            'created_at' => now()->subDays(3),
            'votes_count' => 3,
            'comment_count' => 5,
            'views' => 40,
        ]);

        // Test recent sort
        $recentRequest = new Request(['sort_by' => 'created_at', 'sort_dir' => 'desc']);
        $byRecent = $this->service->getPosts($recentRequest);
        $this->assertEquals('Newest', $byRecent->first()->title);

        // Test votes sort
        $votesRequest = new Request(['sort_by' => 'votes_count', 'sort_dir' => 'desc']);
        $byVotes = $this->service->getPosts($votesRequest);
        $this->assertEquals('Most votes', $byVotes->first()->title);

        // Test comments sort
        $commentsRequest = new Request(['sort_by' => 'comments', 'sort_dir' => 'desc']);
        $byComments = $this->service->getPosts($commentsRequest);
        $this->assertEquals('Most comments', $byComments->first()->title);

        // Test views sort
        $viewsRequest = new Request(['sort_by' => 'views', 'sort_dir' => 'desc']);
        $byViews = $this->service->getPosts($viewsRequest);
        $this->assertEquals('Most views', $byViews->first()->title);
    }

    public function test_it_filters_by_time_interval(): void
    {
        // Create posts with different timestamps
        Post::factory()->create([
            'title' => 'Very old',
            'created_at' => now()->subDays(40),
        ]);

        Post::factory()->create([
            'title' => 'Week old',
            'created_at' => now()->subDays(6),
        ]);

        Post::factory()->create([
            'title' => 'Recent',
            'created_at' => now()->subHours(12),
        ]);

        // Test 1 day filter
        $dayRequest = new Request(['time_interval' => 1440]);
        $dayFilter = $this->service->getPosts($dayRequest);
        $this->assertCount(1, $dayFilter);
        $this->assertEquals('Recent', $dayFilter->first()->title);

        // Test 7 days filter
        $weekRequest = new Request(['time_interval' => 10080]);
        $weekFilter = $this->service->getPosts($weekRequest);
        $this->assertCount(2, $weekFilter);

        // Test 30 days filter
        $monthRequest = new Request(['time_interval' => 43200]);
        $monthFilter = $this->service->getPosts($monthRequest);
        $this->assertCount(2, $monthFilter);
    }

    public function test_it_creates_post_with_correct_data(): void
    {
        // Login user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Post data
        $postData = [
            'title' => 'Test Post',
            'content' => 'Test content',
            'content_type' => 'text',
        ];

        // Create post
        $post = $this->service->createPost($postData);

        // Assert
        $this->assertEquals('Test Post', $post->title);
        $this->assertEquals('Test content', $post->content);
        $this->assertEquals('text', $post->content_type);
        $this->assertEquals($user->id, $post->user_id);
    }

    public function test_it_imports_post_from_external_source(): void
    {
        // Login user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Import data
        $importData = [
            'title' => 'Imported Post',
            'content' => 'Imported content',
            'url' => 'https://example.com/article',
            'content_type' => 'link',
            'source' => 'external_site',
            'source_name' => 'External Site',
        ];

        // Import post
        $post = $this->service->importPost($importData);

        // Assert
        $this->assertEquals('Imported Post', $post->title);
        $this->assertEquals('Imported content', $post->content);
        $this->assertEquals('https://example.com/article', $post->url);
        $this->assertEquals('link', $post->content_type);
        $this->assertEquals('external_site', $post->source);
        $this->assertEquals('External Site', $post->source_name);
        $this->assertFalse($post->is_original);
    }

    public function test_it_updates_post_correctly(): void
    {
        // Create user and post
        $user = User::factory()->create();
        $this->actingAs($user);

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Original title',
            'content' => 'Original content',
            'content_type' => 'text',
        ]);

        // Update data
        $updateData = [
            'title' => 'Updated title',
            'content' => 'Updated content',
        ];

        // Update post
        $updatedPost = $this->service->updatePost($post, $updateData);

        // Assert
        $this->assertEquals('Updated title', $updatedPost->title);
        $this->assertEquals('Updated content', $updatedPost->content);
    }

    public function test_it_ignores_time_interval_when_sorting_by_last_active(): void
    {
        Post::factory()->create([
            'title' => 'Very old',
            'created_at' => now()->subDays(40),
        ]);

        Post::factory()->create([
            'title' => 'Week old',
            'created_at' => now()->subDays(6),
        ]);

        Post::factory()->create([
            'title' => 'Recent',
            'created_at' => now()->subHours(12),
        ]);

        $request = new Request([
            'time_interval' => 1440,
            'sort_by' => 'lastActive',
            'sort_dir' => 'desc',
        ]);

        $result = $this->service->getPosts($request);

        $this->assertCount(3, $result);

        $this->assertEquals('Recent', $result[0]->title);
        $this->assertEquals('Week old', $result[1]->title);
        $this->assertEquals('Very old', $result[2]->title);
    }

    public function test_it_creates_poll_with_correct_metadata(): void
    {
        // Login user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Poll data
        $pollData = [
            'title' => 'Test Poll',
            'content' => 'What is your favorite color?',
            'content_type' => 'poll',
            'poll_options' => ['Red', 'Blue', 'Green'],
            'expires_at' => now()->addDays(7)->toDateTimeString(),
            'allow_multiple_options' => false,
        ];

        // Create poll
        $post = $this->service->createPost($pollData);

        // Assert
        $this->assertEquals('Test Poll', $post->title);
        $this->assertEquals('What is your favorite color?', $post->content);
        $this->assertEquals('poll', $post->content_type);
        $this->assertNotNull($post->media_metadata);

        // Decode and verify media_metadata
        $metadata = is_string($post->media_metadata)
            ? json_decode($post->media_metadata, true)
            : $post->media_metadata;

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('poll_options', $metadata);
        $this->assertEquals(['Red', 'Blue', 'Green'], $metadata['poll_options']);
        $this->assertArrayHasKey('expires_at', $metadata);
        $this->assertArrayHasKey('allow_multiple_options', $metadata);
        $this->assertFalse($metadata['allow_multiple_options']);
    }

    public function test_it_updates_poll_with_new_options(): void
    {
        // Create user and poll
        $user = User::factory()->create();
        $this->actingAs($user);

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Original Poll',
            'content' => 'Original question?',
            'content_type' => 'poll',
            'media_metadata' => json_encode([
                'poll_options' => ['Option 1', 'Option 2'],
                'expires_at' => now()->addDays(5)->toDateTimeString(),
                'allow_multiple_options' => false,
            ]),
        ]);

        // Update data
        $updateData = [
            'title' => 'Updated Poll',
            'content' => 'Updated question?',
            'content_type' => 'poll',
            'poll_options' => ['New Option 1', 'New Option 2', 'New Option 3'],
            'expires_at' => now()->addDays(10)->toDateTimeString(),
            'allow_multiple_options' => true,
        ];

        // Update poll
        $updatedPost = $this->service->updatePost($post, $updateData);

        // Assert
        $this->assertEquals('Updated Poll', $updatedPost->title);
        $this->assertEquals('Updated question?', $updatedPost->content);

        // Decode and verify updated media_metadata
        $metadata = is_string($updatedPost->media_metadata)
            ? json_decode($updatedPost->media_metadata, true)
            : $updatedPost->media_metadata;

        $this->assertIsArray($metadata);
        $this->assertEquals(['New Option 1', 'New Option 2', 'New Option 3'], $metadata['poll_options']);
        $this->assertTrue($metadata['allow_multiple_options']);
    }

    public function test_it_filters_posts_by_single_language(): void
    {
        // Create posts in different languages
        $spanishPosts = Post::factory()->count(3)->create([
            'language_code' => 'es',
            'title' => 'Post en español',
        ]);

        $englishPosts = Post::factory()->count(2)->create([
            'language_code' => 'en',
            'title' => 'Post in English',
        ]);

        $frenchPosts = Post::factory()->count(2)->create([
            'language_code' => 'fr',
            'title' => 'Post en français',
        ]);

        // Test filtering by Spanish only
        $request = new Request(['languages' => 'es']);
        $result = $this->service->getPosts($request);

        $this->assertCount(3, $result);
        foreach ($result as $post) {
            $this->assertEquals('es', $post->language_code);
        }
    }

    public function test_it_filters_posts_by_multiple_languages(): void
    {
        // Create posts in different languages
        Post::factory()->count(3)->create(['language_code' => 'es']);
        Post::factory()->count(2)->create(['language_code' => 'en']);
        Post::factory()->count(2)->create(['language_code' => 'fr']);
        Post::factory()->count(1)->create(['language_code' => 'de']);

        // Test filtering by Spanish and English
        $request = new Request(['languages' => 'es,en']);
        $result = $this->service->getPosts($request);

        $this->assertCount(5, $result);
        foreach ($result as $post) {
            $this->assertContains($post->language_code, ['es', 'en']);
        }
    }

    public function test_it_filters_posts_by_languages_as_array(): void
    {
        // Create posts in different languages
        Post::factory()->count(2)->create(['language_code' => 'es']);
        Post::factory()->count(2)->create(['language_code' => 'en']);
        Post::factory()->count(2)->create(['language_code' => 'fr']);

        // Test filtering with array parameter
        $request = new Request(['languages' => ['es', 'fr']]);
        $result = $this->service->getPosts($request);

        $this->assertCount(4, $result);
        foreach ($result as $post) {
            $this->assertContains($post->language_code, ['es', 'fr']);
        }
    }

    public function test_it_shows_all_languages_when_filter_not_provided(): void
    {
        // Create posts in different languages
        Post::factory()->count(2)->create(['language_code' => 'es']);
        Post::factory()->count(2)->create(['language_code' => 'en']);
        Post::factory()->count(2)->create(['language_code' => 'fr']);

        // Test without language filter
        $request = new Request([]);
        $result = $this->service->getPosts($request);

        $this->assertCount(6, $result);
    }

    public function test_it_filters_frontpage_posts_by_language(): void
    {
        // Create frontpage posts in different languages
        Post::factory()->count(2)->create([
            'language_code' => 'es',
            'votes_count' => 4,
            'frontpage_at' => now(),
        ]);

        Post::factory()->count(2)->create([
            'language_code' => 'en',
            'votes_count' => 4,
            'frontpage_at' => now(),
        ]);

        // Test frontpage with language filter
        $request = new Request([
            'section' => 'frontpage',
            'languages' => 'es',
        ]);

        $result = $this->service->getPosts($request);

        $this->assertCount(2, $result);
        foreach ($result as $post) {
            $this->assertEquals('es', $post->language_code);
        }
    }

    public function test_it_filters_pending_posts_by_language(): void
    {
        // Disable low activity boost for consistent testing
        config(['posts.low_activity_boost_enabled' => false]);

        // Create pending posts in different languages
        Post::factory()->count(2)->create([
            'language_code' => 'es',
            'votes_count' => 1,
        ]);

        Post::factory()->count(2)->create([
            'language_code' => 'en',
            'votes_count' => 1,
        ]);

        // Test pending with language filter
        $request = new Request([
            'section' => 'pending',
            'languages' => 'en',
        ]);

        $result = $this->service->getPosts($request);

        $this->assertCount(2, $result);
        foreach ($result as $post) {
            $this->assertEquals('en', $post->language_code);
        }
    }

    public function test_it_handles_empty_language_filter(): void
    {
        // Create posts in different languages
        Post::factory()->count(3)->create(['language_code' => 'es']);
        Post::factory()->count(2)->create(['language_code' => 'en']);

        // Test with empty string
        $request = new Request(['languages' => '']);
        $result = $this->service->getPosts($request);

        // Should return all posts when filter is empty
        $this->assertCount(5, $result);
    }

    public function test_it_trims_whitespace_in_language_codes(): void
    {
        // Create posts in different languages
        Post::factory()->count(2)->create(['language_code' => 'es']);
        Post::factory()->count(2)->create(['language_code' => 'en']);

        // Test with whitespace in language codes
        $request = new Request(['languages' => ' es , en ']);
        $result = $this->service->getPosts($request);

        $this->assertCount(4, $result);
        foreach ($result as $post) {
            $this->assertContains($post->language_code, ['es', 'en']);
        }
    }

    public function test_cursor_pagination_returns_different_posts_on_each_page(): void
    {
        // Create 10 posts with frontpage_at set (for frontpage section)
        $posts = collect();
        for ($i = 0; $i < 10; $i++) {
            $posts->push(Post::factory()->create([
                'frontpage_at' => now()->subMinutes($i),
                'title' => "Post {$i}",
            ]));
        }

        // First request - get first 3 posts with cursor pagination
        $request = new Request([
            'section' => 'frontpage',
            'pagination' => 'cursor',
            'per_page' => 3,
        ]);

        $firstPage = $this->service->getPosts($request);

        $this->assertCount(3, $firstPage);
        $this->assertTrue($firstPage->hasMorePages());

        $firstPageIds = $firstPage->pluck('id')->toArray();
        $nextCursor = $firstPage->nextCursor()?->encode();

        $this->assertNotNull($nextCursor, 'Next cursor should be set');

        // Second request - use the cursor to get next 3 posts
        $request2 = new Request([
            'section' => 'frontpage',
            'pagination' => 'cursor',
            'cursor' => $nextCursor,
            'per_page' => 3,
        ]);

        $secondPage = $this->service->getPosts($request2);

        $this->assertCount(3, $secondPage);
        $secondPageIds = $secondPage->pluck('id')->toArray();

        // Verify second page has different posts than first page
        foreach ($secondPageIds as $id) {
            $this->assertNotContains(
                $id,
                $firstPageIds,
                'Second page should not contain posts from first page',
            );
        }

        // Third request - get next 3 posts
        $nextCursor2 = $secondPage->nextCursor()?->encode();
        $this->assertNotNull($nextCursor2, 'Second page should have next cursor');

        $request3 = new Request([
            'section' => 'frontpage',
            'pagination' => 'cursor',
            'cursor' => $nextCursor2,
            'per_page' => 3,
        ]);

        $thirdPage = $this->service->getPosts($request3);

        $this->assertCount(3, $thirdPage);
        $thirdPageIds = $thirdPage->pluck('id')->toArray();

        // Verify third page has different posts than first and second pages
        foreach ($thirdPageIds as $id) {
            $this->assertNotContains(
                $id,
                $firstPageIds,
                'Third page should not contain posts from first page',
            );
            $this->assertNotContains(
                $id,
                $secondPageIds,
                'Third page should not contain posts from second page',
            );
        }

        // Verify we've seen 9 unique posts across all pages
        $allIds = array_merge($firstPageIds, $secondPageIds, $thirdPageIds);
        $this->assertCount(9, array_unique($allIds), 'Should have 9 unique posts across 3 pages');
    }

    public function test_it_dispatches_delete_activity_when_federated_post_changes_to_draft(): void
    {
        Queue::fake();
        config(['activitypub.enabled' => true]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
        ]);

        // Mark post as federated
        ActivityPubPostSettings::create([
            'post_id' => $post->id,
            'should_federate' => true,
            'is_federated' => true,
        ]);

        // Change status to draft
        $this->service->updatePostStatus($post, 'draft');

        Queue::assertPushed(DeliverPostDelete::class);
    }

    public function test_it_dispatches_delete_activity_when_federated_post_changes_to_hidden(): void
    {
        Queue::fake();
        config(['activitypub.enabled' => true]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
        ]);

        // Mark post as federated
        ActivityPubPostSettings::create([
            'post_id' => $post->id,
            'should_federate' => true,
            'is_federated' => true,
        ]);

        // Change status to hidden
        $this->service->updatePostStatus($post, 'hidden');

        Queue::assertPushed(DeliverPostDelete::class);
    }

    public function test_it_does_not_dispatch_delete_when_non_federated_post_changes_to_draft(): void
    {
        Queue::fake();
        config(['activitypub.enabled' => true]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
        ]);

        // Post has no ActivityPubPostSettings or is_federated is false
        ActivityPubPostSettings::create([
            'post_id' => $post->id,
            'should_federate' => true,
            'is_federated' => false, // Never actually federated
        ]);

        // Change status to draft
        $this->service->updatePostStatus($post, 'draft');

        Queue::assertNotPushed(DeliverPostDelete::class);
    }

    public function test_it_dispatches_federation_when_draft_changes_to_published_with_should_federate(): void
    {
        Queue::fake();
        config(['activitypub.enabled' => true]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'draft',
        ]);

        // User wants to federate this post
        ActivityPubPostSettings::create([
            'post_id' => $post->id,
            'should_federate' => true,
            'is_federated' => false,
        ]);

        // Enable auto-publish for federation
        \App\Models\SystemSetting::set('federation_auto_publish', true);

        // Change status to published
        $this->service->updatePostStatus($post, 'published');

        Queue::assertPushed(DeliverMultiActorPost::class);
    }

    public function test_it_does_not_dispatch_federation_when_activitypub_disabled(): void
    {
        Queue::fake();
        config(['activitypub.enabled' => false]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
        ]);

        // Mark post as federated
        ActivityPubPostSettings::create([
            'post_id' => $post->id,
            'should_federate' => true,
            'is_federated' => true,
        ]);

        // Change status to draft
        $this->service->updatePostStatus($post, 'draft');

        // Should not dispatch because ActivityPub is disabled
        Queue::assertNotPushed(DeliverPostDelete::class);
    }

    public function test_update_post_dispatches_delete_when_status_changes_to_draft(): void
    {
        Queue::fake();
        config(['activitypub.enabled' => true]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
            'title' => 'Original title',
        ]);

        // Mark post as federated
        ActivityPubPostSettings::create([
            'post_id' => $post->id,
            'should_federate' => true,
            'is_federated' => true,
        ]);

        // Update post with status change to draft
        $this->service->updatePost($post, [
            'title' => 'Updated title',
            'status' => 'draft',
        ]);

        Queue::assertPushed(DeliverPostDelete::class);
    }
}
