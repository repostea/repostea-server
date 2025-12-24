<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use App\Services\CommentVoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CommentVoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private CommentVoteService $service;

    private Comment $comment;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CommentVoteService();
        $this->user = User::factory()->create();
        $post = Post::factory()->create();
        $this->comment = Comment::factory()->create([
            'post_id' => $post->id,
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);
    }

    public function test_it_can_vote_positive_on_a_comment(): void
    {
        $result = $this->service->voteComment(
            $this->comment,
            Vote::VALUE_POSITIVE,
            Vote::TYPE_DIDACTIC,
        );

        $this->assertEquals(__('messages.votes.recorded'), $result['message']);
        $this->assertTrue($result['success']);
        $this->assertEquals(Vote::VALUE_POSITIVE, $result['user_vote']);
        $this->assertEquals(Vote::TYPE_DIDACTIC, $result['user_vote_type']);

        $this->assertDatabaseHas('votes', [
            'user_id' => $this->user->id,
            'votable_id' => $this->comment->id,
            'votable_type' => Comment::class,
            'value' => Vote::VALUE_POSITIVE,
            'type' => Vote::TYPE_DIDACTIC,
        ]);
    }

    public function test_it_can_vote_negative_on_a_comment(): void
    {
        $result = $this->service->voteComment(
            $this->comment,
            Vote::VALUE_NEGATIVE,
            Vote::TYPE_INCOMPLETE,
        );

        $this->assertEquals(__('messages.votes.recorded'), $result['message']);
        $this->assertTrue($result['success']);
        $this->assertEquals(Vote::VALUE_NEGATIVE, $result['user_vote']);
        $this->assertEquals(Vote::TYPE_INCOMPLETE, $result['user_vote_type']);

        $this->assertDatabaseHas('votes', [
            'user_id' => $this->user->id,
            'votable_id' => $this->comment->id,
            'votable_type' => Comment::class,
            'value' => Vote::VALUE_NEGATIVE,
            'type' => Vote::TYPE_INCOMPLETE,
        ]);
    }

    public function test_it_rejects_invalid_vote_type(): void
    {
        $result = $this->service->voteComment(
            $this->comment,
            Vote::VALUE_POSITIVE,
            Vote::TYPE_INCOMPLETE,
        );

        $this->assertEquals(__('votes.invalid_type'), $result['message']);
        $this->assertFalse($result['success']);

        $this->assertDatabaseMissing('votes', [
            'user_id' => $this->user->id,
            'votable_id' => $this->comment->id,
        ]);
    }

    public function test_it_can_update_an_existing_vote(): void
    {
        Vote::create([
            'user_id' => $this->user->id,
            'votable_id' => $this->comment->id,
            'votable_type' => Comment::class,
            'value' => Vote::VALUE_POSITIVE,
            'type' => Vote::TYPE_DIDACTIC,
        ]);

        $result = $this->service->voteComment(
            $this->comment,
            Vote::VALUE_NEGATIVE,
            Vote::TYPE_IRRELEVANT,
        );

        $this->assertEquals(__('messages.votes.updated'), $result['message']);
        $this->assertTrue($result['success']);

        $this->assertDatabaseHas('votes', [
            'user_id' => $this->user->id,
            'votable_id' => $this->comment->id,
            'votable_type' => Comment::class,
            'value' => Vote::VALUE_NEGATIVE,
            'type' => Vote::TYPE_IRRELEVANT,
        ]);
    }

    public function test_it_can_unvote_a_comment(): void
    {
        $vote = Vote::create([
            'user_id' => $this->user->id,
            'votable_id' => $this->comment->id,
            'votable_type' => Comment::class,
            'value' => Vote::VALUE_POSITIVE,
            'type' => Vote::TYPE_DIDACTIC,
        ]);

        $result = $this->service->unvoteComment($this->comment);

        $this->assertEquals(__('messages.votes.removed'), $result['message']);
        $this->assertTrue($result['success']);

        $this->assertDatabaseMissing('votes', [
            'id' => $vote->id,
        ]);
    }

    public function test_it_validates_vote_types_correctly(): void
    {
        $this->assertTrue($this->service->isValidVoteType(Vote::VALUE_POSITIVE, Vote::TYPE_INTERESTING));
        $this->assertTrue($this->service->isValidVoteType(Vote::VALUE_POSITIVE, Vote::TYPE_DIDACTIC));
        $this->assertTrue($this->service->isValidVoteType(Vote::VALUE_POSITIVE, Vote::TYPE_ELABORATE));
        $this->assertTrue($this->service->isValidVoteType(Vote::VALUE_POSITIVE, Vote::TYPE_FUNNY));

        $this->assertTrue($this->service->isValidVoteType(Vote::VALUE_NEGATIVE, Vote::TYPE_INCOMPLETE));
        $this->assertTrue($this->service->isValidVoteType(Vote::VALUE_NEGATIVE, Vote::TYPE_IRRELEVANT));
        $this->assertTrue($this->service->isValidVoteType(Vote::VALUE_NEGATIVE, Vote::TYPE_FALSE));
        $this->assertTrue($this->service->isValidVoteType(Vote::VALUE_NEGATIVE, Vote::TYPE_OUTOFPLACE));

        $this->assertFalse($this->service->isValidVoteType(Vote::VALUE_NEGATIVE, Vote::TYPE_INTERESTING));
        $this->assertFalse($this->service->isValidVoteType(Vote::VALUE_NEGATIVE, Vote::TYPE_DIDACTIC));
        $this->assertFalse($this->service->isValidVoteType(Vote::VALUE_NEGATIVE, Vote::TYPE_ELABORATE));
        $this->assertFalse($this->service->isValidVoteType(Vote::VALUE_NEGATIVE, Vote::TYPE_FUNNY));

        $this->assertFalse($this->service->isValidVoteType(Vote::VALUE_POSITIVE, Vote::TYPE_INCOMPLETE));
        $this->assertFalse($this->service->isValidVoteType(Vote::VALUE_POSITIVE, Vote::TYPE_IRRELEVANT));
        $this->assertFalse($this->service->isValidVoteType(Vote::VALUE_POSITIVE, Vote::TYPE_FALSE));
        $this->assertFalse($this->service->isValidVoteType(Vote::VALUE_POSITIVE, Vote::TYPE_OUTOFPLACE));
    }

    public function test_it_returns_valid_vote_types(): void
    {
        $positiveTypes = $this->service->getValidVoteTypes(Vote::VALUE_POSITIVE);
        $negativeTypes = $this->service->getValidVoteTypes(Vote::VALUE_NEGATIVE);

        $this->assertContains(Vote::TYPE_INTERESTING, $positiveTypes);
        $this->assertContains(Vote::TYPE_DIDACTIC, $positiveTypes);
        $this->assertContains(Vote::TYPE_ELABORATE, $positiveTypes);
        $this->assertContains(Vote::TYPE_FUNNY, $positiveTypes);

        $this->assertContains(Vote::TYPE_IRRELEVANT, $negativeTypes);
        $this->assertContains(Vote::TYPE_INCOMPLETE, $negativeTypes);
        $this->assertContains(Vote::TYPE_FALSE, $negativeTypes);
        $this->assertContains(Vote::TYPE_OUTOFPLACE, $negativeTypes);
    }
}
