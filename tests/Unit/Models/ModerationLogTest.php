<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Comment;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ModerationLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_moderation_log(): void
    {
        $moderator = User::factory()->create();
        $targetUser = User::factory()->create();

        $log = ModerationLog::create([
            'moderator_id' => $moderator->id,
            'target_user_id' => $targetUser->id,
            'action' => 'ban_user',
            'reason' => 'Spam posting',
            'created_at' => now(),
        ]);

        $this->assertInstanceOf(ModerationLog::class, $log);
        $this->assertEquals($moderator->id, $log->moderator_id);
        $this->assertEquals($targetUser->id, $log->target_user_id);
        $this->assertEquals('ban_user', $log->action);
        $this->assertEquals('Spam posting', $log->reason);
    }

    public function test_it_belongs_to_moderator(): void
    {
        $moderator = User::factory()->create();

        $log = ModerationLog::create([
            'moderator_id' => $moderator->id,
            'action' => 'delete_post',
            'created_at' => now(),
        ]);

        $this->assertInstanceOf(User::class, $log->moderator);
        $this->assertEquals($moderator->id, $log->moderator->id);
    }

    public function test_it_belongs_to_target_user(): void
    {
        $moderator = User::factory()->create();
        $targetUser = User::factory()->create();

        $log = ModerationLog::create([
            'moderator_id' => $moderator->id,
            'target_user_id' => $targetUser->id,
            'action' => 'warn_user',
            'created_at' => now(),
        ]);

        $this->assertInstanceOf(User::class, $log->targetUser);
        $this->assertEquals($targetUser->id, $log->targetUser->id);
    }

    public function test_it_has_polymorphic_target_for_post(): void
    {
        $moderator = User::factory()->create();
        $post = Post::factory()->create();

        $log = ModerationLog::create([
            'moderator_id' => $moderator->id,
            'action' => 'delete_post',
            'target_type' => Post::class,
            'target_id' => $post->id,
            'created_at' => now(),
        ]);

        $this->assertInstanceOf(Post::class, $log->target);
        $this->assertEquals($post->id, $log->target->id);
    }

    public function test_it_has_polymorphic_target_for_comment(): void
    {
        $moderator = User::factory()->create();
        $post = Post::factory()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id]);

        $log = ModerationLog::create([
            'moderator_id' => $moderator->id,
            'action' => 'delete_comment',
            'target_type' => Comment::class,
            'target_id' => $comment->id,
            'created_at' => now(),
        ]);

        $this->assertInstanceOf(Comment::class, $log->target);
        $this->assertEquals($comment->id, $log->target->id);
    }

    public function test_it_casts_metadata_to_array(): void
    {
        $moderator = User::factory()->create();

        $log = ModerationLog::create([
            'moderator_id' => $moderator->id,
            'action' => 'edit_post',
            'metadata' => ['old_title' => 'Old Title', 'new_title' => 'New Title'],
            'created_at' => now(),
        ]);

        $this->assertIsArray($log->metadata);
        $this->assertEquals(['old_title' => 'Old Title', 'new_title' => 'New Title'], $log->metadata);
    }

    public function test_it_casts_created_at_to_datetime(): void
    {
        $moderator = User::factory()->create();
        $createdAt = now();

        $log = ModerationLog::create([
            'moderator_id' => $moderator->id,
            'action' => 'approve_post',
            'created_at' => $createdAt,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $log->created_at);
    }

    public function test_it_can_store_metadata(): void
    {
        $moderator = User::factory()->create();

        $log = ModerationLog::create([
            'moderator_id' => $moderator->id,
            'action' => 'update_user',
            'metadata' => [
                'field' => 'email',
                'old_value' => 'old@example.com',
                'new_value' => 'new@example.com',
                'ip_address' => '192.168.1.1',
            ],
            'created_at' => now(),
        ]);

        $this->assertEquals('email', $log->metadata['field']);
        $this->assertEquals('old@example.com', $log->metadata['old_value']);
        $this->assertEquals('new@example.com', $log->metadata['new_value']);
        $this->assertEquals('192.168.1.1', $log->metadata['ip_address']);
    }

    public function test_it_can_log_action_using_static_method(): void
    {
        $moderator = User::factory()->create();
        $targetUser = User::factory()->create();

        $log = ModerationLog::logAction(
            moderatorId: $moderator->id,
            action: 'suspend_user',
            targetUserId: $targetUser->id,
            reason: 'Violated community guidelines',
        );

        $this->assertInstanceOf(ModerationLog::class, $log);
        $this->assertEquals($moderator->id, $log->moderator_id);
        $this->assertEquals($targetUser->id, $log->target_user_id);
        $this->assertEquals('suspend_user', $log->action);
        $this->assertEquals('Violated community guidelines', $log->reason);
    }

    public function test_it_can_log_action_with_target_entity(): void
    {
        $moderator = User::factory()->create();
        $post = Post::factory()->create();

        $log = ModerationLog::logAction(
            moderatorId: $moderator->id,
            action: 'remove_post',
            targetType: Post::class,
            targetId: $post->id,
            reason: 'Inappropriate content',
            metadata: ['reported_count' => 5],
        );

        $this->assertEquals(Post::class, $log->target_type);
        $this->assertEquals($post->id, $log->target_id);
        $this->assertEquals('Inappropriate content', $log->reason);
        $this->assertEquals(['reported_count' => 5], $log->metadata);
    }

    public function test_it_can_log_action_without_optional_parameters(): void
    {
        $moderator = User::factory()->create();

        $log = ModerationLog::logAction(
            moderatorId: $moderator->id,
            action: 'review_reports',
        );

        $this->assertEquals($moderator->id, $log->moderator_id);
        $this->assertEquals('review_reports', $log->action);
        $this->assertNull($log->target_user_id);
        $this->assertNull($log->target_type);
        $this->assertNull($log->target_id);
        $this->assertNull($log->reason);
        $this->assertNull($log->metadata);
    }

    public function test_it_handles_empty_metadata(): void
    {
        $moderator = User::factory()->create();

        $log = ModerationLog::create([
            'moderator_id' => $moderator->id,
            'action' => 'test_action',
            'metadata' => [],
            'created_at' => now(),
        ]);

        $this->assertIsArray($log->metadata);
        $this->assertEmpty($log->metadata);
    }

    public function test_it_does_not_have_updated_at_timestamp(): void
    {
        $moderator = User::factory()->create();

        $log = ModerationLog::create([
            'moderator_id' => $moderator->id,
            'action' => 'test_action',
            'created_at' => now(),
        ]);

        $this->assertNull($log->updated_at);
    }

    public function test_it_records_different_moderation_actions(): void
    {
        $moderator = User::factory()->create();

        $actions = ['ban_user', 'delete_post', 'delete_comment', 'approve_post', 'reject_report', 'warn_user'];

        foreach ($actions as $action) {
            $log = ModerationLog::create([
                'moderator_id' => $moderator->id,
                'action' => $action,
                'created_at' => now(),
            ]);

            $this->assertEquals($action, $log->action);
        }

        $this->assertEquals(count($actions), ModerationLog::count());
    }

    public function test_multiple_moderators_can_log_actions(): void
    {
        $moderator1 = User::factory()->create();
        $moderator2 = User::factory()->create();

        ModerationLog::create([
            'moderator_id' => $moderator1->id,
            'action' => 'action1',
            'created_at' => now(),
        ]);

        ModerationLog::create([
            'moderator_id' => $moderator2->id,
            'action' => 'action2',
            'created_at' => now(),
        ]);

        $this->assertEquals(2, ModerationLog::count());
        $this->assertEquals(1, ModerationLog::where('moderator_id', $moderator1->id)->count());
        $this->assertEquals(1, ModerationLog::where('moderator_id', $moderator2->id)->count());
    }

    public function test_it_can_track_moderator_actions_on_multiple_users(): void
    {
        $moderator = User::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        ModerationLog::create([
            'moderator_id' => $moderator->id,
            'target_user_id' => $user1->id,
            'action' => 'warn_user',
            'created_at' => now(),
        ]);

        ModerationLog::create([
            'moderator_id' => $moderator->id,
            'target_user_id' => $user2->id,
            'action' => 'ban_user',
            'created_at' => now(),
        ]);

        $this->assertEquals(2, ModerationLog::where('moderator_id', $moderator->id)->count());
    }
}
