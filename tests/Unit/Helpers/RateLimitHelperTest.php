<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\RateLimitHelper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class RateLimitHelperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup test rate limit configuration
        Config::set('rate_limits.actions.test_action', [
            'max_attempts' => 5,
            'decay_minutes' => 1,
            'use_karma_multiplier' => false,
        ]);

        Config::set('rate_limits.actions.test_karma_action', [
            'max_attempts' => 10,
            'decay_minutes' => 1,
            'use_karma_multiplier' => true,
        ]);

        Config::set('rate_limits.karma_multipliers', [
            0 => 1.0,
            100 => 1.5,
            500 => 2.0,
            1000 => 2.5,
        ]);

        Cache::flush();
    }

    public function test_it_checks_rate_limit_for_user(): void
    {
        $user = User::factory()->create();

        $result = RateLimitHelper::check('test_action', $user->id);

        $this->assertFalse($result['exceeded']);
        $this->assertEquals(5, $result['remaining']);
        $this->assertNull($result['retry_after']);
    }

    public function test_it_checks_rate_limit_for_ip(): void
    {
        $result = RateLimitHelper::check('test_action', null, '192.168.1.1');

        $this->assertFalse($result['exceeded']);
        $this->assertEquals(5, $result['remaining']);
        $this->assertNull($result['retry_after']);
    }

    public function test_it_increments_rate_limit(): void
    {
        $user = User::factory()->create();

        RateLimitHelper::increment('test_action', $user->id);
        $attempts = RateLimitHelper::getAttempts('test_action', $user->id);

        $this->assertEquals(1, $attempts);

        RateLimitHelper::increment('test_action', $user->id);
        $attempts = RateLimitHelper::getAttempts('test_action', $user->id);

        $this->assertEquals(2, $attempts);
    }

    public function test_it_detects_exceeded_rate_limit(): void
    {
        $user = User::factory()->create();

        // Increment 5 times to reach limit
        for ($i = 0; $i < 5; $i++) {
            RateLimitHelper::increment('test_action', $user->id);
        }

        $result = RateLimitHelper::check('test_action', $user->id);

        $this->assertTrue($result['exceeded']);
        $this->assertEquals(0, $result['remaining']);
        $this->assertNotNull($result['retry_after']);
    }

    public function test_it_resets_rate_limit(): void
    {
        $user = User::factory()->create();

        RateLimitHelper::increment('test_action', $user->id);
        $this->assertEquals(1, RateLimitHelper::getAttempts('test_action', $user->id));

        RateLimitHelper::reset('test_action', $user->id);
        $this->assertEquals(0, RateLimitHelper::getAttempts('test_action', $user->id));
    }

    public function test_it_applies_karma_multiplier(): void
    {
        $user = User::factory()->create(['karma_points' => 150]);

        $result = RateLimitHelper::check('test_karma_action', $user->id);

        // Base limit is 10, with 150 karma (100-499 range) multiplier is 1.5 = 15
        $this->assertEquals(15, $result['max_attempts']);
        $this->assertEquals(15, $result['remaining']);
    }

    public function test_it_handles_high_karma_multiplier(): void
    {
        $user = User::factory()->create(['karma_points' => 1500]);

        $result = RateLimitHelper::check('test_karma_action', $user->id);

        // Base limit is 10, with 1500 karma (>1000) multiplier is 2.5 = 25
        $this->assertEquals(25, $result['max_attempts']);
        $this->assertEquals(25, $result['remaining']);
    }

    public function test_it_returns_false_for_unknown_action(): void
    {
        $result = RateLimitHelper::check('unknown_action', 1);

        $this->assertFalse($result['exceeded']);
        $this->assertEquals(999, $result['remaining']);
        $this->assertNull($result['retry_after']);
    }

    public function test_it_gets_all_limits(): void
    {
        $limits = RateLimitHelper::getAllLimits();

        $this->assertIsArray($limits);
        $this->assertArrayHasKey('test_action', $limits);
    }

    public function test_it_gets_specific_limit(): void
    {
        $limit = RateLimitHelper::getLimit('test_action');

        $this->assertIsArray($limit);
        $this->assertEquals(5, $limit['max_attempts']);
        $this->assertEquals(1, $limit['decay_minutes']);
    }

    public function test_it_checks_ip_blacklist(): void
    {
        $result = RateLimitHelper::isIpBlacklisted('192.168.1.1');

        $this->assertFalse($result['blacklisted']);
        $this->assertNull($result['reason']);
    }

    public function test_it_detects_blacklisted_ip(): void
    {
        Cache::put('ip_blacklist:10.0.0.1', [
            'reason' => 'Spam',
            'blacklisted_at' => '2025-01-01',
            'blacklisted_by' => 'admin',
        ], 60);

        $result = RateLimitHelper::isIpBlacklisted('10.0.0.1');

        $this->assertTrue($result['blacklisted']);
        $this->assertEquals('Spam', $result['reason']);
        $this->assertEquals('2025-01-01', $result['blacklisted_at']);
        $this->assertEquals('admin', $result['blacklisted_by']);
    }

    public function test_it_builds_correct_cache_key_for_user(): void
    {
        $user = User::factory()->create();
        RateLimitHelper::increment('test_action', $user->id);

        $key = "rate_limit:test_action:user:{$user->id}";
        $this->assertTrue(Cache::has($key));
    }

    public function test_it_builds_correct_cache_key_for_ip(): void
    {
        RateLimitHelper::increment('test_action', null, '192.168.1.1');

        $key = 'rate_limit:test_action:ip:192.168.1.1';
        $this->assertTrue(Cache::has($key));
    }
}
