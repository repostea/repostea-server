<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CommentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $post;

    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('auth_token')->plainTextToken;

        $this->post = Post::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_can_list_comments_for_a_post(): void
    {
        $comments = Comment::factory()->count(3)->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
            'parent_id' => null,
        ]);

        $reply = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
            'parent_id' => $comments[0]->id,
        ]);

        $response = $this->getJson("/api/v1/posts/{$this->post->id}/comments");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'content', 'post_id', 'parent_id',
                        'user', 'replies',
                    ],
                ],
            ]);

        $response->assertJson([
            'data' => [
                [
                    'id' => $comments[0]->id,
                    'replies' => [
                        ['id' => $reply->id],
                    ],
                ],
            ],
        ]);
    }

    #[Test]
    public function it_can_create_a_new_comment(): void
    {
        $commentData = [
            'content' => 'Este es un comentario de prueba',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/posts/{$this->post->id}/comments", $commentData);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'content' => 'Este es un comentario de prueba',
                    'post_id' => $this->post->id,
                ],
            ]);

        $this->assertDatabaseHas('comments', [
            'content' => 'Este es un comentario de prueba',
            'user_id' => $this->user->id,
            'post_id' => $this->post->id,
        ]);

        $this->post->refresh();
        $this->assertEquals(1, $this->post->comment_count);
    }

    #[Test]
    public function it_can_create_a_reply_to_a_comment(): void
    {
        $parentComment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
        ]);

        $replyData = [
            'content' => 'Esta es una respuesta a un comentario',
            'parent_id' => $parentComment->id,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/posts/{$this->post->id}/comments", $replyData);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'content' => 'Esta es una respuesta a un comentario',
                    'parent_id' => $parentComment->id,
                ],
            ]);

        $this->assertDatabaseHas('comments', [
            'content' => 'Esta es una respuesta a un comentario',
            'parent_id' => $parentComment->id,
        ]);
    }

    #[Test]
    public function user_can_update_their_own_comment(): void
    {
        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
            'content' => 'Comentario original',
        ]);

        $updateData = [
            'content' => 'Comentario actualizado',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/comments/{$comment->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'content' => 'Comentario actualizado',
                ],
            ]);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'content' => 'Comentario actualizado',
        ]);
    }

    #[Test]
    public function user_cannot_update_others_comments(): void
    {
        $otherUser = User::factory()->create();

        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $otherUser->id,
            'content' => 'Comentario de otro usuario',
        ]);

        $updateData = [
            'content' => 'Intento de actualizar comentario ajeno',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/comments/{$comment->id}", $updateData);

        $response->assertStatus(403);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'content' => 'Comentario de otro usuario',
        ]);
    }

    #[Test]
    public function user_can_delete_their_own_comment(): void
    {
        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/comments/{$comment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', __('messages.comments.deleted'));

        // Verify comment status changed to deleted
        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'status' => 'deleted_by_author',
            'content' => '[deleted]',
        ]);

        $this->post->refresh();
        $this->assertEquals(0, $this->post->comment_count);
    }

    #[Test]
    public function user_can_vote_on_a_comment(): void
    {
        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
        ]);

        $voteData = [
            'value' => 1,
            'type' => 'didactic',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/comments/{$comment->id}/vote", $voteData);

        $response->assertStatus(200)
            ->assertJsonPath('message', __('messages.votes.recorded'))
            ->assertJsonPath('data.user_vote', 1)
            ->assertJsonPath('data.user_vote_type', 'didactic')
            ->assertJsonStructure([
                'message',
                'data' => [
                    'stats' => [
                        'votes_count',
                        'vote_details',
                        'vote_types',
                    ],
                    'user_vote',
                    'user_vote_type',
                ],
            ]);

        $this->assertDatabaseHas('votes', [
            'user_id' => $this->user->id,
            'votable_id' => $comment->id,
            'votable_type' => Comment::class,
            'value' => 1,
            'type' => 'didactic',
        ]);
    }

    #[Test]
    public function user_can_unvote_a_comment(): void
    {
        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
        ]);

        $vote = Vote::create([
            'user_id' => $this->user->id,
            'votable_id' => $comment->id,
            'votable_type' => Comment::class,
            'value' => 1,
            'type' => 'didactic',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/comments/{$comment->id}/vote");

        $response->assertStatus(200)
            ->assertJsonPath('message', __('messages.votes.removed'))
            ->assertJsonStructure([
                'message',
                'stats' => [
                    'votes_count',
                    'vote_details',
                    'vote_types',
                ],
            ]);

        $this->assertDatabaseMissing('votes', [
            'id' => $vote->id,
        ]);
    }

    #[Test]
    public function it_can_get_vote_stats_for_a_comment(): void
    {
        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
        ]);

        Vote::create([
            'user_id' => $this->user->id,
            'votable_id' => $comment->id,
            'votable_type' => Comment::class,
            'value' => 1,
            'type' => 'didactic',
        ]);

        $otherUser = User::factory()->create();
        Vote::create([
            'user_id' => $otherUser->id,
            'votable_id' => $comment->id,
            'votable_type' => Comment::class,
            'value' => -1,
            'type' => 'incomplete',
        ]);

        $response = $this->getJson("/api/v1/comments/{$comment->id}/vote-stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'votes_count',
                'vote_details',
                'vote_types',
            ]);

        $jsonData = $response->json();

        $this->assertEquals(1, $jsonData['vote_types']['didactic']);
        $this->assertEquals(-1, $jsonData['vote_types']['incomplete']);
        $this->assertCount(2, $jsonData['vote_details']);
    }
}
