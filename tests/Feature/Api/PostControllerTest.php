<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache
        Cache::flush();

        // Disable low activity boost for consistent testing
        config(['posts.low_activity_boost_enabled' => false]);

        // Create test user
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('auth_token')->plainTextToken;
    }

    #[Test]
    public function it_can_list_all_posts(): void
    {
        // Create multiple posts
        $posts = Post::factory()->count(5)->create([
            'user_id' => $this->user->id,
        ]);

        // Make request
        $response = $this->getJson('/api/v1/posts');

        // Verify response
        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'title', 'content', 'content_type',
                    ],
                ],
                'meta',
            ]);
    }

    #[Test]
    public function it_can_filter_posts_by_user(): void
    {
        // Create another user
        $otherUser = User::factory()->create();

        // Create posts for both users
        $userPosts = Post::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $otherUserPosts = Post::factory()->count(2)->create([
            'user_id' => $otherUser->id,
        ]);

        // Make request filtered by user
        $response = $this->getJson("/api/v1/posts?user_id={$this->user->id}");

        // Verify response only includes user posts
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');

        // Verify only correct user posts are included
        $postIds = collect($response->json('data'))->pluck('id')->all();
        $userPostIds = $userPosts->pluck('id')->all();

        sort($postIds);
        sort($userPostIds);

        $this->assertEquals($userPostIds, $postIds);
    }

    #[Test]
    public function it_can_display_frontpage_posts(): void
    {
        // Create posts that have reached frontpage (have frontpage_at set)
        $frontpagePosts = Post::factory()->count(3)->create([
            'votes_count' => 10,
            'frontpage_at' => now(),
        ]);

        // Create posts with low vote counts for pending (no frontpage_at)
        $pendingPosts = Post::factory()->count(2)->create([
            'votes_count' => 2,
            'frontpage_at' => null,
        ]);

        // Make request to frontpage endpoint
        $response = $this->getJson('/api/v1/posts/frontpage');

        // Verify response only includes frontpage posts
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');

        // Verify correct post ids are returned
        $returnedIds = collect($response->json('data'))->pluck('id')->toArray();
        $frontpageIds = $frontpagePosts->pluck('id')->toArray();

        sort($returnedIds);
        sort($frontpageIds);

        $this->assertEquals($frontpageIds, $returnedIds);
    }

    #[Test]
    public function it_can_display_pending_posts(): void
    {
        // Create frontpage posts (have frontpage_at set)
        Post::factory()->count(3)->create([
            'votes_count' => 10,
            'frontpage_at' => now(),
        ]);

        // Create pending posts (no frontpage_at, low votes)
        $pendingPosts = Post::factory()->count(2)->create([
            'votes_count' => 0,
            'frontpage_at' => null,
        ]);

        // Make request to pending endpoint
        $response = $this->getJson('/api/v1/posts/pending');

        // Verify response only includes pending posts
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Verify correct post ids are returned
        $returnedIds = collect($response->json('data'))->pluck('id')->toArray();
        $pendingIds = $pendingPosts->pluck('id')->toArray();

        sort($returnedIds);
        sort($pendingIds);

        $this->assertEquals($pendingIds, $returnedIds);
    }

    #[Test]
    public function it_can_sort_posts_by_comments_count(): void
    {
        // Create posts with different comment counts
        Post::factory()->create([
            'comment_count' => 10,
            'title' => 'Most commented',
        ]);

        Post::factory()->create([
            'comment_count' => 5,
            'title' => 'Medium commented',
        ]);

        Post::factory()->create([
            'comment_count' => 1,
            'title' => 'Least commented',
        ]);

        // Request sorted by comments
        $response = $this->getJson('/api/v1/posts?sort_by=comments&sort_dir=desc');

        // Verify correct order
        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title')->toArray();

        $this->assertEquals([
            'Most commented',
            'Medium commented',
            'Least commented',
        ], $titles);
    }

    #[Test]
    public function it_can_sort_posts_by_views(): void
    {
        // Create posts with different view counts
        Post::factory()->create([
            'views' => 100,
            'title' => 'Most viewed',
        ]);

        Post::factory()->create([
            'views' => 50,
            'title' => 'Medium viewed',
        ]);

        Post::factory()->create([
            'views' => 10,
            'title' => 'Least viewed',
        ]);

        // Request sorted by views
        $response = $this->getJson('/api/v1/posts?sort_by=views&sort_dir=desc');

        // Verify correct order
        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title')->toArray();

        $this->assertEquals([
            'Most viewed',
            'Medium viewed',
            'Least viewed',
        ], $titles);
    }

    #[Test]
    public function it_can_filter_posts_by_time_interval(): void
    {
        // Create old post
        Post::factory()->create([
            'title' => 'Old post',
            'created_at' => now()->subDays(40),
        ]);

        // Create recent post
        Post::factory()->create([
            'title' => 'Recent post',
            'created_at' => now()->subDays(5),
        ]);

        // Request with 7 day interval
        $response = $this->getJson('/api/v1/posts?time_interval=10080');

        // Verify only recent post is returned
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertEquals(['Recent post'], $titles);
    }

    // Mantener todos los tests existentes...
    #[Test]
    public function it_can_show_a_specific_post(): void
    {
        // Create a post
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Post de prueba',
            'content' => 'Contenido del post de prueba',
        ]);

        // Make request
        $response = $this->getJson("/api/v1/posts/{$post->id}");

        // Verify response
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $post->id,
                    'title' => 'Post de prueba',
                    'content' => 'Contenido del post de prueba',
                ],
            ]);

        // Verify response with proper structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id', 'title', 'content', 'user', 'created_at', 'updated_at',
                ],
            ]);
    }

    #[Test]
    public function it_can_create_a_new_post(): void
    {
        // Data for new post
        $postData = [
            'title' => 'Nuevo post de prueba',
            'content' => 'Este es el contenido del nuevo post',
            'content_type' => 'text',
        ];

        // Make request to create
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/posts', $postData);

        // Verify response
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'title' => 'Nuevo post de prueba',
                    'content' => 'Este es el contenido del nuevo post',
                    'content_type' => 'text',
                ],
            ]);

        // Verify saved in database
        $this->assertDatabaseHas('posts', [
            'title' => 'Nuevo post de prueba',
            'content' => 'Este es el contenido del nuevo post',
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_can_create_a_link_post(): void
    {
        // Data for link post
        $postData = [
            'title' => 'Post con enlace',
            'content' => 'Descripción del enlace',
            'url' => 'https://example.com',
            'content_type' => 'link',
        ];

        // Make request to create
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/posts', $postData);

        // Verify response
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'title' => 'Post con enlace',
                    'content' => 'Descripción del enlace',
                    'url' => 'https://example.com',
                    'content_type' => 'link',
                ],
            ]);

        // Verify saved in database
        $this->assertDatabaseHas('posts', [
            'title' => 'Post con enlace',
            'url' => 'https://example.com',
            'content_type' => 'link',
        ]);
    }

    #[Test]
    public function it_can_update_a_post(): void
    {
        // Create user post
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Post original',
            'content' => 'Contenido original',
            'content_type' => 'text',
        ]);

        // Update data
        $updateData = [
            'title' => 'Post actualizado',
            'content' => 'Contenido actualizado',
            'content_type' => 'text',
        ];

        // Make update request
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/posts/{$post->id}", $updateData);

        // Verify response
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $post->id,
                    'title' => 'Post actualizado',
                    'content' => 'Contenido actualizado',
                ],
            ]);

        // Verify updated in database
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Post actualizado',
            'content' => 'Contenido actualizado',
        ]);
    }

    #[Test]
    public function user_cannot_update_posts_of_other_users(): void
    {
        // Create another user
        $otherUser = User::factory()->create();

        // Create post from other user with text content type
        $post = Post::factory()->create([
            'user_id' => $otherUser->id,
            'title' => 'Post de otro usuario',
            'content' => 'Contenido de otro usuario',
            'content_type' => 'text',
        ]);

        // Update data
        $updateData = [
            'title' => 'Intento de actualizar post ajeno',
            'content' => 'Contenido modificado',
            'content_type' => 'text',
        ];

        // Make update request
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/posts/{$post->id}", $updateData);

        // Verify access denied
        $response->assertStatus(403);

        // Verify not modified in database
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Post de otro usuario',
            'content' => 'Contenido de otro usuario',
        ]);
    }

    #[Test]
    public function user_cannot_delete_posts_of_other_users(): void
    {
        // Create another user
        $otherUser = User::factory()->create();

        // Create post from other user
        $post = Post::factory()->create([
            'user_id' => $otherUser->id,
            'title' => 'Post de otro usuario',
            'content' => 'Contenido de otro usuario',
            'content_type' => 'text',
            'comment_count' => 0,
        ]);

        // Try to delete other user's post
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/posts/{$post->id}");

        // Verify access denied
        $response->assertStatus(403);

        // Verify post still exists and is NOT deleted
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Post de otro usuario',
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_can_delete_a_post(): void
    {
        // Create user post with comment_count = 0 explicitly
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Post para eliminar',
            'content' => 'Este post será eliminado',
            'content_type' => 'text',
            'comment_count' => 0,
        ]);

        // Make delete request
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/posts/{$post->id}");

        // Verify response
        $response->assertStatus(200)
            ->assertJsonPath('message', __('messages.posts.deleted'));

        // Verify soft deleted from database
        $this->assertSoftDeleted('posts', [
            'id' => $post->id,
        ]);
    }

    #[Test]
    public function user_can_vote_on_a_post(): void
    {
        // Create a post
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $voteData = [
            'value' => 1,
            'type' => 'interesting',
        ];

        // Make vote request
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/posts/{$post->id}/vote", $voteData);

        // Verify response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'votes',
            ]);

        $this->assertDatabaseHas('votes', [
            'user_id' => $this->user->id,
            'votable_id' => $post->id,
            'votable_type' => Post::class,
            'value' => 1,
            'type' => 'interesting',
        ]);
    }

    #[Test]
    public function user_can_update_their_vote_on_a_post(): void
    {
        // Create a post
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
        ]);

        Vote::create([
            'user_id' => $this->user->id,
            'votable_id' => $post->id,
            'votable_type' => Post::class,
            'value' => 1,
            'type' => 'interesting',
        ]);

        $voteData = [
            'value' => 1,
            'type' => 'elaborate',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/posts/{$post->id}/vote", $voteData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('votes', [
            'user_id' => $this->user->id,
            'votable_id' => $post->id,
            'votable_type' => Post::class,
            'value' => 1,
            'type' => 'elaborate',
        ]);
    }

    #[Test]
    public function user_can_unvote_a_post(): void
    {
        // Create a post
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Create previous vote
        $vote = Vote::create([
            'user_id' => $this->user->id,
            'votable_id' => $post->id,
            'votable_type' => Post::class,
            'value' => 1,
            'type' => 'interesting',
        ]);

        // Make unvote request
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/posts/{$post->id}/vote");

        // Verify response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Vote removed.',
            ]);

        // Verify vote removed
        $this->assertDatabaseMissing('votes', [
            'id' => $vote->id,
        ]);
    }

    #[Test]
    public function it_can_get_vote_stats_for_a_post(): void
    {
        // Create a post
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
        ]);

        Vote::create([
            'user_id' => $this->user->id,
            'votable_id' => $post->id,
            'votable_type' => Post::class,
            'value' => 1,
            'type' => 'interesting',
        ]);

        $otherUser = User::factory()->create();
        Vote::create([
            'user_id' => $otherUser->id,
            'votable_id' => $post->id,
            'votable_type' => Post::class,
            'value' => 1,
            'type' => 'interesting',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/posts/{$post->id}/vote-stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'vote_types',
                'total_upvotes',
                'total_votes',
                'vote_score',
            ]);

        $response->assertJson([
            'total_upvotes' => 2,
            'total_votes' => 2,
            'vote_score' => 2,
        ]);
    }

    #[Test]
    public function it_can_register_a_view_for_a_post(): void
    {
        // Create a post
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'views' => 0,
        ]);

        $initialViewCount = $post->views;

        // Make request to register view
        $response = $this->postJson("/api/v1/posts/{$post->id}/view");

        // Verify response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'View registered successfully.',
            ]);

        // Verify database record was created
        $this->assertDatabaseHas('post_views', [
            'post_id' => $post->id,
            'ip_address' => '127.0.0.1', // CRITICAL: Verify correct column name
            'user_agent' => 'Symfony',
        ]);

        // Verify post view count was incremented
        $post->refresh();
        $this->assertEquals($initialViewCount + 1, $post->views);
    }

    #[Test]
    public function it_prevents_duplicate_views_from_same_ip_via_endpoint(): void
    {
        // Create a post
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'views' => 0,
        ]);

        // Register first view
        $firstResponse = $this->postJson("/api/v1/posts/{$post->id}/view");
        $firstResponse->assertStatus(200);

        // Try to register second view from same IP (should be blocked by cache)
        $secondResponse = $this->postJson("/api/v1/posts/{$post->id}/view");
        $secondResponse->assertStatus(200)
            ->assertJson([
                'message' => 'View already registered recently.',
                'success' => false,
            ]);

        // Only one database record should exist
        $this->assertDatabaseCount('post_views', 1);

        // Post view count should only be incremented once
        $post->refresh();
        $this->assertEquals(1, $post->views);
    }

    #[Test]
    public function view_endpoint_handles_authenticated_users(): void
    {
        // Create a post
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'views' => 0,
        ]);

        // Make authenticated request
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/posts/{$post->id}/view");

        $response->assertStatus(200);

        // Verify user_id is recorded for authenticated requests
        $this->assertDatabaseHas('post_views', [
            'post_id' => $post->id,
            'user_id' => $this->user->id,
            'ip_address' => '127.0.0.1',
        ]);
    }

    #[Test]
    public function it_can_create_a_poll_post(): void
    {
        // Data for poll post
        $pollData = [
            'title' => 'Poll: Favorite Programming Language',
            'content' => 'What is your favorite programming language?',
            'content_type' => 'poll',
            'poll_options' => ['PHP', 'JavaScript', 'Python', 'Ruby'],
            'expires_at' => now()->addDays(7)->toDateTimeString(),
            'allow_multiple_options' => false,
        ];

        // Make request to create
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/posts', $pollData);

        // Verify response
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'title' => 'Poll: Favorite Programming Language',
                    'content' => 'What is your favorite programming language?',
                    'content_type' => 'poll',
                ],
            ]);

        // Get the created post to check media_metadata
        $postId = $response->json('data.id');
        $post = Post::find($postId);

        // Verify media_metadata contains poll options
        $metadata = is_string($post->media_metadata)
            ? json_decode($post->media_metadata, true)
            : $post->media_metadata;

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('poll_options', $metadata);
        $this->assertEquals(['PHP', 'JavaScript', 'Python', 'Ruby'], $metadata['poll_options']);
        $this->assertArrayHasKey('expires_at', $metadata);
        $this->assertArrayHasKey('allow_multiple_options', $metadata);
        $this->assertFalse($metadata['allow_multiple_options']);
    }

    #[Test]
    public function it_validates_poll_requires_at_least_two_options(): void
    {
        // Poll with only one option
        $pollData = [
            'title' => 'Invalid Poll',
            'content' => 'This poll should fail',
            'content_type' => 'poll',
            'poll_options' => ['Only one option'],
        ];

        // Make request to create
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/posts', $pollData);

        // Verify validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors('poll_options');
    }

    #[Test]
    public function it_validates_poll_cannot_have_more_than_ten_options(): void
    {
        // Poll with too many options
        $pollData = [
            'title' => 'Invalid Poll',
            'content' => 'This poll has too many options',
            'content_type' => 'poll',
            'poll_options' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11'],
        ];

        // Make request to create
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/posts', $pollData);

        // Verify validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors('poll_options');
    }

    #[Test]
    public function it_can_update_a_poll_post(): void
    {
        // Create poll post
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Poll',
            'content' => 'Original question?',
            'content_type' => 'poll',
            'media_metadata' => json_encode([
                'poll_options' => ['Option A', 'Option B'],
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

        // Make update request
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/posts/{$post->id}", $updateData);

        // Verify response
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $post->id,
                    'title' => 'Updated Poll',
                    'content' => 'Updated question?',
                ],
            ]);

        // Refresh and verify media_metadata
        $post->refresh();
        $metadata = is_string($post->media_metadata)
            ? json_decode($post->media_metadata, true)
            : $post->media_metadata;

        $this->assertEquals(['New Option 1', 'New Option 2', 'New Option 3'], $metadata['poll_options']);
        $this->assertTrue($metadata['allow_multiple_options']);
    }

    #[Test]
    public function it_can_create_nsfw_post(): void
    {
        $postData = [
            'title' => 'NSFW Content Post',
            'content' => 'This is NSFW content',
            'content_type' => 'text',
            'is_nsfw' => true,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/posts', $postData);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'title' => 'NSFW Content Post',
                    'is_nsfw' => true,
                ],
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'NSFW Content Post',
            'is_nsfw' => true,
        ]);
    }

    #[Test]
    public function it_can_create_non_nsfw_post_by_default(): void
    {
        $postData = [
            'title' => 'Regular Content Post',
            'content' => 'This is regular content',
            'content_type' => 'text',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/posts', $postData);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'title' => 'Regular Content Post',
                    'is_nsfw' => false,
                ],
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Regular Content Post',
            'is_nsfw' => false,
        ]);
    }

    #[Test]
    public function it_filters_nsfw_posts_when_user_preference_is_enabled(): void
    {
        // Create user preferences with hide_nsfw enabled
        $this->user->preferences()->create([
            'hide_nsfw' => true,
        ]);

        // Create NSFW and non-NSFW posts
        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'NSFW Post',
            'is_nsfw' => true,
        ]);

        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Safe Post',
            'is_nsfw' => false,
        ]);

        // Make authenticated request
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/posts');

        $response->assertStatus(200);

        // Verify only non-NSFW post is returned
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Safe Post', $titles);
        $this->assertNotContains('NSFW Post', $titles);
    }

    #[Test]
    public function it_shows_nsfw_posts_when_user_preference_is_disabled(): void
    {
        // Create user preferences with hide_nsfw disabled
        $this->user->preferences()->create([
            'hide_nsfw' => false,
        ]);

        // Create NSFW and non-NSFW posts
        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'NSFW Post',
            'is_nsfw' => true,
        ]);

        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Safe Post',
            'is_nsfw' => false,
        ]);

        // Make authenticated request
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/posts');

        $response->assertStatus(200);

        // Verify both posts are returned
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Safe Post', $titles);
        $this->assertContains('NSFW Post', $titles);
    }

    #[Test]
    public function it_shows_all_posts_for_unauthenticated_users(): void
    {
        // Create NSFW and non-NSFW posts
        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'NSFW Post',
            'is_nsfw' => true,
        ]);

        Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Safe Post',
            'is_nsfw' => false,
        ]);

        // Make unauthenticated request
        $response = $this->getJson('/api/v1/posts');

        $response->assertStatus(200);

        // Verify both posts are returned (no filter for anonymous users)
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Safe Post', $titles);
        $this->assertContains('NSFW Post', $titles);
    }

    #[Test]
    public function it_can_update_nsfw_flag_on_existing_post(): void
    {
        // Create a non-NSFW post
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Regular Post',
            'content' => 'Regular content',
            'content_type' => 'text',
            'is_nsfw' => false,
        ]);

        // Update to mark as NSFW
        $updateData = [
            'title' => 'Regular Post',
            'content' => 'Regular content',
            'content_type' => 'text',
            'is_nsfw' => true,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/posts/{$post->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'is_nsfw' => true,
                ],
            ]);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'is_nsfw' => true,
        ]);
    }
}
