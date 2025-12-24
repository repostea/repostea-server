<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Post;
use App\Models\PostRelationship;
use App\Models\User;
use App\Services\RelationshipAchievementChecker;
use App\Services\RelationshipVoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RelationshipVoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private RelationshipVoteService $service;

    private PostRelationship $relationship;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $achievementChecker = new RelationshipAchievementChecker();
        $this->service = new RelationshipVoteService($achievementChecker);

        $this->user = User::factory()->create();
        $sourcePost = Post::factory()->create();
        $targetPost = Post::factory()->create();

        $this->relationship = PostRelationship::create([
            'source_post_id' => $sourcePost->id,
            'target_post_id' => $targetPost->id,
            'relationship_type' => 'related',
            'relation_category' => 'own',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_it_can_vote_on_a_relationship(): void
    {
        $result = $this->service->vote($this->relationship->id, $this->user->id, 1);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Vote registered successfully.', $result['message']);
        $this->assertArrayHasKey('vote', $result);

        $this->assertDatabaseHas('relationship_votes', [
            'relationship_id' => $this->relationship->id,
            'user_id' => $this->user->id,
            'vote' => 1,
        ]);

        $this->relationship->refresh();
        $this->assertEquals(1, $this->relationship->upvotes_count);
        $this->assertEquals(0, $this->relationship->downvotes_count);
        $this->assertEquals(1, $this->relationship->score);
    }

    public function test_it_can_downvote_a_relationship(): void
    {
        $result = $this->service->vote($this->relationship->id, $this->user->id, -1);

        $this->assertEquals('success', $result['status']);

        $this->assertDatabaseHas('relationship_votes', [
            'relationship_id' => $this->relationship->id,
            'user_id' => $this->user->id,
            'vote' => -1,
        ]);

        $this->relationship->refresh();
        $this->assertEquals(0, $this->relationship->upvotes_count);
        $this->assertEquals(1, $this->relationship->downvotes_count);
        $this->assertEquals(-1, $this->relationship->score);
    }

    public function test_it_removes_vote_when_same_vote_is_cast_again(): void
    {
        // First vote
        $this->service->vote($this->relationship->id, $this->user->id, 1);

        // Same vote again (toggle)
        $result = $this->service->vote($this->relationship->id, $this->user->id, 1);

        $this->assertEquals('removed', $result['status']);
        $this->assertEquals('Vote removed successfully.', $result['message']);

        $this->assertDatabaseMissing('relationship_votes', [
            'relationship_id' => $this->relationship->id,
            'user_id' => $this->user->id,
        ]);

        $this->relationship->refresh();
        $this->assertEquals(0, $this->relationship->upvotes_count);
        $this->assertEquals(0, $this->relationship->downvotes_count);
        $this->assertEquals(0, $this->relationship->score);
    }

    public function test_it_changes_vote_when_different_vote_is_cast(): void
    {
        // First vote: upvote
        $this->service->vote($this->relationship->id, $this->user->id, 1);

        // Change to downvote
        $result = $this->service->vote($this->relationship->id, $this->user->id, -1);

        $this->assertEquals('success', $result['status']);

        $this->assertDatabaseHas('relationship_votes', [
            'relationship_id' => $this->relationship->id,
            'user_id' => $this->user->id,
            'vote' => -1,
        ]);

        $this->relationship->refresh();
        $this->assertEquals(0, $this->relationship->upvotes_count);
        $this->assertEquals(1, $this->relationship->downvotes_count);
        $this->assertEquals(-1, $this->relationship->score);
    }

    public function test_it_validates_vote_value(): void
    {
        $result = $this->service->vote($this->relationship->id, $this->user->id, 5);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Invalid vote value. Must be 1 or -1.', $result['message']);

        $this->assertDatabaseMissing('relationship_votes', [
            'relationship_id' => $this->relationship->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_it_returns_error_for_non_existent_relationship(): void
    {
        $result = $this->service->vote(99999, $this->user->id, 1);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Relationship not found.', $result['message']);
    }

    public function test_it_calculates_correct_score_with_multiple_votes(): void
    {
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $user4 = User::factory()->create();

        // 3 upvotes
        $this->service->vote($this->relationship->id, $this->user->id, 1);
        $this->service->vote($this->relationship->id, $user2->id, 1);
        $this->service->vote($this->relationship->id, $user3->id, 1);

        // 1 downvote
        $this->service->vote($this->relationship->id, $user4->id, -1);

        $this->relationship->refresh();
        $this->assertEquals(3, $this->relationship->upvotes_count);
        $this->assertEquals(1, $this->relationship->downvotes_count);
        $this->assertEquals(2, $this->relationship->score); // 3 - 1 = 2
    }

    public function test_it_returns_user_vote(): void
    {
        $this->service->vote($this->relationship->id, $this->user->id, 1);

        $userVote = $this->service->getUserVote($this->relationship->id, $this->user->id);

        $this->assertNotNull($userVote);
        $this->assertEquals(1, $userVote->vote);
        $this->assertEquals($this->user->id, $userVote->user_id);
        $this->assertEquals($this->relationship->id, $userVote->relationship_id);
    }

    public function test_it_returns_null_when_user_has_not_voted(): void
    {
        $userVote = $this->service->getUserVote($this->relationship->id, $this->user->id);

        $this->assertNull($userVote);
    }

    public function test_it_returns_correct_vote_stats(): void
    {
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $this->service->vote($this->relationship->id, $this->user->id, 1);
        $this->service->vote($this->relationship->id, $user2->id, 1);
        $this->service->vote($this->relationship->id, $user3->id, -1);

        $stats = $this->service->getVoteStats($this->relationship->id);

        $this->assertEquals(2, $stats['upvotes']);
        $this->assertEquals(1, $stats['downvotes']);
        $this->assertEquals(1, $stats['score']);
    }

    public function test_it_returns_zero_stats_for_non_existent_relationship(): void
    {
        $stats = $this->service->getVoteStats(99999);

        $this->assertEquals(0, $stats['upvotes']);
        $this->assertEquals(0, $stats['downvotes']);
        $this->assertEquals(0, $stats['score']);
    }
}
