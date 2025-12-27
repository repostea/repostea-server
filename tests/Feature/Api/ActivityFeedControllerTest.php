<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AgoraMessage;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Sub;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ActivityFeedControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Sub $sub;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->user = User::factory()->create();
        $this->sub = Sub::create([
            'name' => 'testsub',
            'display_name' => 'Test Sub',
            'created_by' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_excludes_draft_posts_from_activity_feed(): void
    {
        // Create a published post
        $publishedPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => Post::STATUS_PUBLISHED,
            'title' => 'Published Post',
        ]);

        // Create a draft post
        $draftPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => Post::STATUS_DRAFT,
            'title' => 'Draft Post',
        ]);

        $response = $this->getJson('/api/v1/activities/feed?types=new_post');

        $response->assertStatus(200);

        $activities = $response->json('activities');
        $postTitles = collect($activities)->pluck('title')->all();

        $this->assertContains('Published Post', $postTitles);
        $this->assertNotContains('Draft Post', $postTitles);
    }

    #[Test]
    public function it_excludes_hidden_posts_from_activity_feed(): void
    {
        // Create a published post
        $publishedPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => Post::STATUS_PUBLISHED,
            'title' => 'Published Post',
        ]);

        // Create a hidden post
        $hiddenPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => Post::STATUS_HIDDEN,
            'title' => 'Hidden Post',
        ]);

        $response = $this->getJson('/api/v1/activities/feed?types=new_post');

        $response->assertStatus(200);

        $activities = $response->json('activities');
        $postTitles = collect($activities)->pluck('title')->all();

        $this->assertContains('Published Post', $postTitles);
        $this->assertNotContains('Hidden Post', $postTitles);
    }

    #[Test]
    public function it_excludes_pending_posts_from_activity_feed(): void
    {
        // Create a published post
        $publishedPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => Post::STATUS_PUBLISHED,
            'title' => 'Published Post',
        ]);

        // Create a pending post
        $pendingPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => Post::STATUS_PENDING,
            'title' => 'Pending Post',
        ]);

        $response = $this->getJson('/api/v1/activities/feed?types=new_post');

        $response->assertStatus(200);

        $activities = $response->json('activities');
        $postTitles = collect($activities)->pluck('title')->all();

        $this->assertContains('Published Post', $postTitles);
        $this->assertNotContains('Pending Post', $postTitles);
    }

    #[Test]
    public function it_excludes_votes_on_draft_posts_from_activity_feed(): void
    {
        $voter = User::factory()->create();

        // Create a published post with a vote
        $publishedPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => Post::STATUS_PUBLISHED,
            'title' => 'Published Post',
        ]);

        Vote::create([
            'user_id' => $voter->id,
            'votable_id' => $publishedPost->id,
            'votable_type' => Post::class,
            'value' => Vote::VALUE_POSITIVE,
            'type' => 'interesting',
        ]);

        // Create a draft post with a vote
        $draftPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => Post::STATUS_DRAFT,
            'title' => 'Draft Post',
        ]);

        Vote::create([
            'user_id' => $voter->id,
            'votable_id' => $draftPost->id,
            'votable_type' => Post::class,
            'value' => Vote::VALUE_POSITIVE,
            'type' => 'interesting',
        ]);

        $response = $this->getJson('/api/v1/activities/feed?types=post_vote');

        $response->assertStatus(200);

        $activities = $response->json('activities');
        $postTitles = collect($activities)->pluck('post_title')->all();

        $this->assertContains('Published Post', $postTitles);
        $this->assertNotContains('Draft Post', $postTitles);
    }

    #[Test]
    public function it_excludes_comments_on_draft_posts_from_activity_feed(): void
    {
        // Create a published post with a comment
        $publishedPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => Post::STATUS_PUBLISHED,
            'title' => 'Published Post',
        ]);

        Comment::factory()->create([
            'user_id' => $this->user->id,
            'post_id' => $publishedPost->id,
            'content' => 'Comment on published post',
        ]);

        // Create a draft post with a comment
        $draftPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => Post::STATUS_DRAFT,
            'title' => 'Draft Post',
        ]);

        Comment::factory()->create([
            'user_id' => $this->user->id,
            'post_id' => $draftPost->id,
            'content' => 'Comment on draft post',
        ]);

        $response = $this->getJson('/api/v1/activities/feed?types=new_comment');

        $response->assertStatus(200);

        $activities = $response->json('activities');
        $postTitles = collect($activities)->pluck('post_title')->all();

        $this->assertContains('Published Post', $postTitles);
        $this->assertNotContains('Draft Post', $postTitles);
    }

    #[Test]
    public function it_only_shows_published_posts_in_frontpage_activity(): void
    {
        // Create a published post that reached frontpage
        $publishedPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => Post::STATUS_PUBLISHED,
            'title' => 'Published Frontpage Post',
            'frontpage_at' => now(),
        ]);

        // Create a hidden post that somehow has frontpage_at set (edge case)
        $hiddenPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => Post::STATUS_HIDDEN,
            'title' => 'Hidden Frontpage Post',
            'frontpage_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/activities/feed?types=frontpage');

        $response->assertStatus(200);

        $activities = $response->json('activities');
        $postTitles = collect($activities)->pluck('title')->all();

        $this->assertContains('Published Frontpage Post', $postTitles);
        $this->assertNotContains('Hidden Frontpage Post', $postTitles);
    }

    #[Test]
    public function it_excludes_hidden_comments_from_activity_feed(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'sub_id' => $this->sub->id,
            'status' => Post::STATUS_PUBLISHED,
            'title' => 'Test Post',
        ]);

        // Create a visible comment
        Comment::factory()->create([
            'user_id' => $this->user->id,
            'post_id' => $post->id,
            'content' => 'Visible comment',
            'status' => Comment::STATUS_PUBLISHED,
        ]);

        // Create a hidden comment
        Comment::factory()->create([
            'user_id' => $this->user->id,
            'post_id' => $post->id,
            'content' => 'Hidden comment',
            'status' => Comment::STATUS_HIDDEN,
        ]);

        // Create a deleted by moderator comment
        Comment::factory()->create([
            'user_id' => $this->user->id,
            'post_id' => $post->id,
            'content' => 'Deleted comment',
            'status' => Comment::STATUS_DELETED_BY_MODERATOR,
        ]);

        $response = $this->getJson('/api/v1/activities/feed?types=new_comment');

        $response->assertStatus(200);

        $activities = $response->json('activities');
        $commentContents = collect($activities)->pluck('comment_content')->all();

        $this->assertContains('Visible comment', $commentContents);
        $this->assertNotContains('Hidden comment', $commentContents);
        $this->assertNotContains('Deleted comment', $commentContents);
    }

    #[Test]
    public function it_excludes_deleted_agora_messages_from_activity_feed(): void
    {
        // Create a visible agora message
        $visibleMessage = AgoraMessage::create([
            'user_id' => $this->user->id,
            'content' => 'Visible agora message',
        ]);

        // Create a deleted agora message
        $deletedMessage = AgoraMessage::create([
            'user_id' => $this->user->id,
            'content' => 'Deleted agora message',
        ]);
        $deletedMessage->delete(); // Soft delete

        $response = $this->getJson('/api/v1/activities/feed?types=new_agora_message');

        $response->assertStatus(200);

        $activities = $response->json('activities');
        $messageContents = collect($activities)->pluck('agora_message_content')->all();

        $this->assertContains('Visible agora message', $messageContents);
        $this->assertNotContains('Deleted agora message', $messageContents);
    }

    #[Test]
    public function it_excludes_private_subs_from_activity_feed(): void
    {
        // Create a public sub
        Sub::create([
            'name' => 'publicsub',
            'display_name' => 'Public Sub',
            'created_by' => $this->user->id,
            'is_private' => false,
        ]);

        // Create a private sub
        Sub::create([
            'name' => 'privatesub',
            'display_name' => 'Private Sub',
            'created_by' => $this->user->id,
            'is_private' => true,
        ]);

        $response = $this->getJson('/api/v1/activities/feed?types=new_sub');

        $response->assertStatus(200);

        $activities = $response->json('activities');
        $subNames = collect($activities)->pluck('sub_display_name')->all();

        $this->assertContains('Public Sub', $subNames);
        $this->assertNotContains('Private Sub', $subNames);
    }

    #[Test]
    public function it_excludes_deleted_subs_from_activity_feed(): void
    {
        // Create a normal sub
        Sub::create([
            'name' => 'normalsub',
            'display_name' => 'Normal Sub',
            'created_by' => $this->user->id,
        ]);

        // Create a deleted sub
        $deletedSub = Sub::create([
            'name' => 'deletedsub',
            'display_name' => 'Deleted Sub',
            'created_by' => $this->user->id,
        ]);
        $deletedSub->delete(); // Soft delete

        $response = $this->getJson('/api/v1/activities/feed?types=new_sub');

        $response->assertStatus(200);

        $activities = $response->json('activities');
        $subNames = collect($activities)->pluck('sub_display_name')->all();

        $this->assertContains('Normal Sub', $subNames);
        $this->assertNotContains('Deleted Sub', $subNames);
    }
}
