<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\UserStrike;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserStrikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_user_strike(): void
    {
        $user = User::factory()->create();
        $moderator = User::factory()->create();

        $strike = UserStrike::create([
            'user_id' => $user->id,
            'issued_by' => $moderator->id,
            'type' => 'warning',
            'reason' => 'Spam posting',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(UserStrike::class, $strike);
        $this->assertEquals($user->id, $strike->user_id);
        $this->assertEquals($moderator->id, $strike->issued_by);
        $this->assertEquals('warning', $strike->type);
    }

    public function test_it_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $moderator = User::factory()->create();

        $strike = UserStrike::create([
            'user_id' => $user->id,
            'issued_by' => $moderator->id,
            'type' => 'warning',
            'reason' => 'Test',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(User::class, $strike->user);
        $this->assertEquals($user->id, $strike->user->id);
    }

    public function test_it_belongs_to_issuer(): void
    {
        $user = User::factory()->create();
        $moderator = User::factory()->create();

        $strike = UserStrike::create([
            'user_id' => $user->id,
            'issued_by' => $moderator->id,
            'type' => 'warning',
            'reason' => 'Test',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(User::class, $strike->issuedBy);
        $this->assertEquals($moderator->id, $strike->issuedBy->id);
    }

    public function test_it_can_relate_to_post(): void
    {
        $user = User::factory()->create();
        $moderator = User::factory()->create();
        $post = Post::factory()->create();

        $strike = UserStrike::create([
            'user_id' => $user->id,
            'issued_by' => $moderator->id,
            'type' => 'warning',
            'reason' => 'Spam post',
            'related_post_id' => $post->id,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Post::class, $strike->relatedPost);
        $this->assertEquals($post->id, $strike->relatedPost->id);
    }

    public function test_it_can_relate_to_comment(): void
    {
        $user = User::factory()->create();
        $moderator = User::factory()->create();
        $post = Post::factory()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id]);

        $strike = UserStrike::create([
            'user_id' => $user->id,
            'issued_by' => $moderator->id,
            'type' => 'warning',
            'reason' => 'Spam comment',
            'related_comment_id' => $comment->id,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Comment::class, $strike->relatedComment);
        $this->assertEquals($comment->id, $strike->relatedComment->id);
    }

    public function test_it_checks_if_strike_is_active(): void
    {
        $user = User::factory()->create();
        $moderator = User::factory()->create();

        $strike = UserStrike::create([
            'user_id' => $user->id,
            'issued_by' => $moderator->id,
            'type' => 'warning',
            'reason' => 'Test',
            'is_active' => true,
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertTrue($strike->isActive());
    }

    public function test_it_detects_inactive_strike(): void
    {
        $user = User::factory()->create();
        $moderator = User::factory()->create();

        $strike = UserStrike::create([
            'user_id' => $user->id,
            'issued_by' => $moderator->id,
            'type' => 'warning',
            'reason' => 'Test',
            'is_active' => false,
        ]);

        $this->assertFalse($strike->isActive());
    }

    public function test_it_detects_expired_strike(): void
    {
        $user = User::factory()->create();
        $moderator = User::factory()->create();

        $strike = UserStrike::create([
            'user_id' => $user->id,
            'issued_by' => $moderator->id,
            'type' => 'warning',
            'reason' => 'Test',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertTrue($strike->isExpired());
        $this->assertFalse($strike->isActive());
    }

    public function test_it_handles_permanent_strike(): void
    {
        $user = User::factory()->create();
        $moderator = User::factory()->create();

        $strike = UserStrike::create([
            'user_id' => $user->id,
            'issued_by' => $moderator->id,
            'type' => 'ban',
            'reason' => 'Permanent ban',
            'is_active' => true,
            'expires_at' => null,
        ]);

        $this->assertTrue($strike->isActive());
        $this->assertFalse($strike->isExpired());
    }

    public function test_it_casts_expires_at_to_datetime(): void
    {
        $user = User::factory()->create();
        $moderator = User::factory()->create();
        $expiresAt = now()->addDays(7);

        $strike = UserStrike::create([
            'user_id' => $user->id,
            'issued_by' => $moderator->id,
            'type' => 'warning',
            'reason' => 'Test',
            'is_active' => true,
            'expires_at' => $expiresAt,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $strike->expires_at);
    }

    public function test_it_casts_is_active_to_boolean(): void
    {
        $user = User::factory()->create();
        $moderator = User::factory()->create();

        $strike = UserStrike::create([
            'user_id' => $user->id,
            'issued_by' => $moderator->id,
            'type' => 'warning',
            'reason' => 'Test',
            'is_active' => '1',
        ]);

        $this->assertIsBool($strike->is_active);
        $this->assertTrue($strike->is_active);
    }

    public function test_it_stores_internal_notes(): void
    {
        $user = User::factory()->create();
        $moderator = User::factory()->create();

        $strike = UserStrike::create([
            'user_id' => $user->id,
            'issued_by' => $moderator->id,
            'type' => 'warning',
            'reason' => 'Public reason',
            'internal_notes' => 'Private notes for moderators',
            'is_active' => true,
        ]);

        $this->assertEquals('Private notes for moderators', $strike->internal_notes);
    }

    public function test_it_has_timestamps(): void
    {
        $user = User::factory()->create();
        $moderator = User::factory()->create();

        $strike = UserStrike::create([
            'user_id' => $user->id,
            'issued_by' => $moderator->id,
            'type' => 'warning',
            'reason' => 'Test',
            'is_active' => true,
        ]);

        $this->assertNotNull($strike->created_at);
        $this->assertNotNull($strike->updated_at);
    }
}
