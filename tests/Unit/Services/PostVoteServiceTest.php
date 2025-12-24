<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use App\Services\PostVoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PostVoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private PostVoteService $service;

    private Post $post;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PostVoteService::class);
        $this->user = User::factory()->create();
        $this->post = Post::factory()->create();

        $this->actingAs($this->user);
    }

    public function test_it_can_vote_on_a_post(): void
    {
        $result = $this->service->votePost($this->post, Vote::VALUE_POSITIVE, 'interesting');

        $this->assertEquals(__('messages.votes.recorded'), $result['message']);
        $this->assertEquals(1, $result['votes']);
        $this->assertTrue($result['updated']);

        $this->assertDatabaseHas('votes', [
            'user_id' => $this->user->id,
            'votable_id' => $this->post->id,
            'votable_type' => Post::class,
            'value' => Vote::VALUE_POSITIVE,
            'type' => 'interesting',
        ]);
    }

    public function test_it_returns_already_voted_when_same_vote_exists(): void
    {
        Vote::create([
            'user_id' => $this->user->id,
            'votable_id' => $this->post->id,
            'votable_type' => Post::class,
            'value' => Vote::VALUE_POSITIVE,
            'type' => 'interesting',
        ]);

        $result = $this->service->votePost($this->post, Vote::VALUE_POSITIVE, 'interesting');

        // Verify the already voted message
        $this->assertEquals(__('messages.votes.already_voted'), $result['message']);
        $this->assertEquals(0, $result['votes']);
        $this->assertFalse($result['updated']);
    }

    public function test_it_can_unvote_a_post(): void
    {
        Vote::create([
            'user_id' => $this->user->id,
            'votable_id' => $this->post->id,
            'votable_type' => Post::class,
            'value' => Vote::VALUE_POSITIVE,
            'type' => 'interesting',
        ]);

        $result = $this->service->unvotePost($this->post);

        $this->assertEquals(__('messages.votes.removed'), $result['message']);
        $this->assertEquals(0, $result['votes']);

        $this->assertDatabaseMissing('votes', [
            'user_id' => $this->user->id,
            'votable_id' => $this->post->id,
        ]);
    }

    public function test_it_returns_correct_vote_stats(): void
    {
        Vote::create([
            'user_id' => $this->user->id,
            'votable_id' => $this->post->id,
            'votable_type' => Post::class,
            'value' => Vote::VALUE_POSITIVE,
            'type' => 'interesting',
        ]);

        Vote::create([
            'user_id' => User::factory()->create()->id,
            'votable_id' => $this->post->id,
            'votable_type' => Post::class,
            'value' => Vote::VALUE_POSITIVE,
            'type' => 'interesting',
        ]);

        $stats = $this->service->getVoteStats($this->post);

        $this->assertEquals(2, $stats['total_upvotes']);
        $this->assertEquals(2, $stats['total_votes']);
        $this->assertEquals(2, $stats['vote_score']);
        $this->assertEmpty($stats['vote_types']);
    }
}
