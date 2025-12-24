<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\PollVote;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PollVoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_poll_vote(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['content_type' => 'poll']);

        $pollVote = PollVote::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'option_number' => 1,
        ]);

        $this->assertInstanceOf(PollVote::class, $pollVote);
        $this->assertEquals($post->id, $pollVote->post_id);
        $this->assertEquals($user->id, $pollVote->user_id);
        $this->assertEquals(1, $pollVote->option_number);
    }

    public function test_it_belongs_to_a_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['content_type' => 'poll']);

        $pollVote = PollVote::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'option_number' => 1,
        ]);

        $this->assertInstanceOf(Post::class, $pollVote->post);
        $this->assertEquals($post->id, $pollVote->post->id);
    }

    public function test_it_belongs_to_a_user(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['content_type' => 'poll']);

        $pollVote = PollVote::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'option_number' => 1,
        ]);

        $this->assertInstanceOf(User::class, $pollVote->user);
        $this->assertEquals($user->id, $pollVote->user->id);
    }

    public function test_it_casts_option_number_to_integer(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['content_type' => 'poll']);

        $pollVote = PollVote::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'option_number' => '2',
        ]);

        $this->assertIsInt($pollVote->option_number);
        $this->assertEquals(2, $pollVote->option_number);
    }

    public function test_it_can_store_device_fingerprint(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['content_type' => 'poll']);

        $pollVote = PollVote::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'option_number' => 1,
            'device_fingerprint' => 'abc123fingerprint',
        ]);

        $this->assertEquals('abc123fingerprint', $pollVote->device_fingerprint);
    }

    public function test_it_can_store_ip_address(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['content_type' => 'poll']);

        $pollVote = PollVote::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'option_number' => 1,
            'ip_address' => '192.168.1.1',
        ]);

        $this->assertEquals('192.168.1.1', $pollVote->ip_address);
    }

    public function test_it_has_timestamps(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['content_type' => 'poll']);

        $pollVote = PollVote::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'option_number' => 1,
        ]);

        $this->assertNotNull($pollVote->created_at);
        $this->assertNotNull($pollVote->updated_at);
    }

    public function test_multiple_users_can_vote_on_same_poll(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $post = Post::factory()->create(['content_type' => 'poll']);

        $vote1 = PollVote::create([
            'post_id' => $post->id,
            'user_id' => $user1->id,
            'option_number' => 1,
        ]);

        $vote2 = PollVote::create([
            'post_id' => $post->id,
            'user_id' => $user2->id,
            'option_number' => 2,
        ]);

        $this->assertEquals(2, PollVote::where('post_id', $post->id)->count());
        $this->assertEquals(1, $vote1->option_number);
        $this->assertEquals(2, $vote2->option_number);
    }
}
