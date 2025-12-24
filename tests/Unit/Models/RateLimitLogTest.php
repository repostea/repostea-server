<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\RateLimitLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RateLimitLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_rate_limit_log(): void
    {
        $user = User::factory()->create();

        $log = RateLimitLog::create([
            'user_id' => $user->id,
            'action' => 'create_post',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'attempts' => 15,
            'max_attempts' => 10,
            'endpoint' => '/api/posts',
            'method' => 'POST',
        ]);

        $this->assertInstanceOf(RateLimitLog::class, $log);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertEquals('create_post', $log->action);
        $this->assertEquals('192.168.1.1', $log->ip_address);
        $this->assertEquals(15, $log->attempts);
        $this->assertEquals(10, $log->max_attempts);
    }

    public function test_it_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $log = RateLimitLog::create([
            'user_id' => $user->id,
            'action' => 'vote',
            'ip_address' => '127.0.0.1',
            'attempts' => 5,
            'max_attempts' => 3,
        ]);

        $this->assertInstanceOf(User::class, $log->user);
        $this->assertEquals($user->id, $log->user->id);
    }

    public function test_it_casts_metadata_to_array(): void
    {
        $user = User::factory()->create();

        $log = RateLimitLog::create([
            'user_id' => $user->id,
            'action' => 'test_action',
            'ip_address' => '127.0.0.1',
            'metadata' => ['key1' => 'value1', 'key2' => 'value2'],
            'attempts' => 5,
            'max_attempts' => 3,
        ]);

        $this->assertIsArray($log->metadata);
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $log->metadata);
    }

    public function test_it_casts_attempts_to_integer(): void
    {
        $user = User::factory()->create();

        $log = RateLimitLog::create([
            'user_id' => $user->id,
            'action' => 'test_action',
            'ip_address' => '127.0.0.1',
            'attempts' => '10',
            'max_attempts' => 5,
        ]);

        $this->assertIsInt($log->attempts);
        $this->assertEquals(10, $log->attempts);
    }

    public function test_it_does_not_have_updated_at_timestamp(): void
    {
        $user = User::factory()->create();

        $log = RateLimitLog::create([
            'user_id' => $user->id,
            'action' => 'test_action',
            'ip_address' => '127.0.0.1',
            'attempts' => 5,
            'max_attempts' => 3,
        ]);

        $this->assertNull($log->updated_at);
    }

    public function test_recent_scope_returns_logs_within_timeframe(): void
    {
        $user = User::factory()->create();

        $recent = RateLimitLog::create([
            'user_id' => $user->id,
            'action' => 'recent_action',
            'ip_address' => '127.0.0.1',
            'attempts' => 5,
            'max_attempts' => 3,
        ]);
        $recent->created_at = now()->subHours(12);
        $recent->save();

        $old = RateLimitLog::create([
            'user_id' => $user->id,
            'action' => 'old_action',
            'ip_address' => '127.0.0.1',
            'attempts' => 5,
            'max_attempts' => 3,
        ]);
        $old->created_at = now()->subHours(48);
        $old->save();

        $recentLogs = RateLimitLog::recent(24)->get();

        $this->assertEquals(1, $recentLogs->count());
        $this->assertEquals('recent_action', $recentLogs->first()->action);
    }

    public function test_by_action_scope_filters_by_action(): void
    {
        $user = User::factory()->create();

        RateLimitLog::create([
            'user_id' => $user->id,
            'action' => 'create_post',
            'ip_address' => '127.0.0.1',
            'attempts' => 5,
            'max_attempts' => 3,
        ]);

        RateLimitLog::create([
            'user_id' => $user->id,
            'action' => 'create_comment',
            'ip_address' => '127.0.0.1',
            'attempts' => 5,
            'max_attempts' => 3,
        ]);

        $logs = RateLimitLog::byAction('create_post')->get();

        $this->assertEquals(1, $logs->count());
        $this->assertEquals('create_post', $logs->first()->action);
    }

    public function test_by_user_scope_filters_by_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        RateLimitLog::create([
            'user_id' => $user1->id,
            'action' => 'action1',
            'ip_address' => '127.0.0.1',
            'attempts' => 5,
            'max_attempts' => 3,
        ]);

        RateLimitLog::create([
            'user_id' => $user2->id,
            'action' => 'action2',
            'ip_address' => '127.0.0.1',
            'attempts' => 5,
            'max_attempts' => 3,
        ]);

        $logs = RateLimitLog::byUser($user1->id)->get();

        $this->assertEquals(1, $logs->count());
        $this->assertEquals($user1->id, $logs->first()->user_id);
    }

    public function test_by_ip_scope_filters_by_ip_address(): void
    {
        $user = User::factory()->create();

        RateLimitLog::create([
            'user_id' => $user->id,
            'action' => 'action1',
            'ip_address' => '192.168.1.1',
            'attempts' => 5,
            'max_attempts' => 3,
        ]);

        RateLimitLog::create([
            'user_id' => $user->id,
            'action' => 'action2',
            'ip_address' => '192.168.1.2',
            'attempts' => 5,
            'max_attempts' => 3,
        ]);

        $logs = RateLimitLog::byIp('192.168.1.1')->get();

        $this->assertEquals(1, $logs->count());
        $this->assertEquals('192.168.1.1', $logs->first()->ip_address);
    }

    public function test_it_gets_suspicious_users(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $log = RateLimitLog::create([
                'user_id' => $user1->id,
                'action' => 'action_' . $i,
                'ip_address' => '127.0.0.1',
                'attempts' => 5,
                'max_attempts' => 3,
            ]);
            $log->created_at = now()->subHours(2);
            $log->save();
        }

        for ($i = 0; $i < 2; $i++) {
            $log = RateLimitLog::create([
                'user_id' => $user2->id,
                'action' => 'action_' . $i,
                'ip_address' => '127.0.0.1',
                'attempts' => 5,
                'max_attempts' => 3,
            ]);
            $log->created_at = now()->subHours(2);
            $log->save();
        }

        $suspiciousUsers = RateLimitLog::getSuspiciousUsers(24, 5);

        $this->assertEquals(1, $suspiciousUsers->count());
        $this->assertEquals($user1->id, $suspiciousUsers->first()->user_id);
        $this->assertEquals(6, $suspiciousUsers->first()->violation_count);
    }

    public function test_it_gets_violations_by_action(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            RateLimitLog::create([
                'user_id' => $user->id,
                'action' => 'create_post',
                'ip_address' => '192.168.1.' . $i,
                'attempts' => 5,
                'max_attempts' => 3,
            ]);
        }

        RateLimitLog::create([
            'user_id' => $user->id,
            'action' => 'vote',
            'ip_address' => '127.0.0.1',
            'attempts' => 5,
            'max_attempts' => 3,
        ]);

        $violations = RateLimitLog::getViolationsByAction(24);

        $this->assertEquals(2, $violations->count());
        $this->assertEquals('create_post', $violations->first()->action);
        $this->assertEquals(3, $violations->first()->total_violations);
    }

    public function test_it_can_handle_null_user_id(): void
    {
        $log = RateLimitLog::create([
            'user_id' => null,
            'action' => 'anonymous_action',
            'ip_address' => '192.168.1.1',
            'attempts' => 5,
            'max_attempts' => 3,
        ]);

        $this->assertNull($log->user_id);
        $this->assertEquals('192.168.1.1', $log->ip_address);
    }
}
