<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\IpBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class IpBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_an_ip_block(): void
    {
        $user = User::factory()->create();

        $block = IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Spam attacks',
            'blocked_by' => $user->id,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(IpBlock::class, $block);
        $this->assertEquals('192.168.1.100', $block->ip_address);
        $this->assertEquals('single', $block->type);
        $this->assertEquals('permanent', $block->block_type);
        $this->assertEquals('Spam attacks', $block->reason);
        $this->assertTrue($block->is_active);
    }

    public function test_it_belongs_to_blocked_by_user(): void
    {
        $user = User::factory()->create();

        $block = IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Test',
            'blocked_by' => $user->id,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(User::class, $block->blockedBy);
        $this->assertEquals($user->id, $block->blockedBy->id);
    }

    public function test_it_checks_if_block_is_active(): void
    {
        $block = IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Test',
            'is_active' => true,
        ]);

        $this->assertTrue($block->isActive());
    }

    public function test_it_detects_inactive_block(): void
    {
        $block = IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Test',
            'is_active' => false,
        ]);

        $this->assertFalse($block->isActive());
    }

    public function test_it_detects_expired_block(): void
    {
        $block = IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'temporary',
            'expires_at' => now()->subDay(),
            'reason' => 'Test',
            'is_active' => true,
        ]);

        $this->assertTrue($block->isExpired());
        $this->assertFalse($block->isActive());
    }

    public function test_it_validates_non_expired_block(): void
    {
        $block = IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'temporary',
            'expires_at' => now()->addDays(7),
            'reason' => 'Test',
            'is_active' => true,
        ]);

        $this->assertFalse($block->isExpired());
        $this->assertTrue($block->isActive());
    }

    public function test_it_records_hit(): void
    {
        $block = IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Test',
            'is_active' => true,
            'hit_count' => 0,
        ]);

        $block->recordHit();

        $this->assertEquals(1, $block->fresh()->hit_count);
        $this->assertNotNull($block->fresh()->last_hit_at);
    }

    public function test_it_increments_hit_count_multiple_times(): void
    {
        $block = IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Test',
            'is_active' => true,
            'hit_count' => 0,
        ]);

        $block->recordHit();
        $block->recordHit();
        $block->recordHit();

        $this->assertEquals(3, $block->fresh()->hit_count);
    }

    public function test_active_scope_returns_only_active_blocks(): void
    {
        IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Active',
            'is_active' => true,
        ]);

        IpBlock::create([
            'ip_address' => '192.168.1.101',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Inactive',
            'is_active' => false,
        ]);

        $activeBlocks = IpBlock::active()->get();

        $this->assertEquals(1, $activeBlocks->count());
        $this->assertEquals('192.168.1.100', $activeBlocks->first()->ip_address);
    }

    public function test_permanent_scope_returns_only_permanent_blocks(): void
    {
        IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Permanent',
            'is_active' => true,
        ]);

        IpBlock::create([
            'ip_address' => '192.168.1.101',
            'type' => 'single',
            'block_type' => 'temporary',
            'expires_at' => now()->addDay(),
            'reason' => 'Temporary',
            'is_active' => true,
        ]);

        $permanentBlocks = IpBlock::permanent()->get();

        $this->assertEquals(1, $permanentBlocks->count());
        $this->assertEquals('permanent', $permanentBlocks->first()->block_type);
    }

    public function test_temporary_scope_returns_only_temporary_blocks(): void
    {
        IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Permanent',
            'is_active' => true,
        ]);

        IpBlock::create([
            'ip_address' => '192.168.1.101',
            'type' => 'single',
            'block_type' => 'temporary',
            'expires_at' => now()->addDay(),
            'reason' => 'Temporary',
            'is_active' => true,
        ]);

        $temporaryBlocks = IpBlock::temporary()->get();

        $this->assertEquals(1, $temporaryBlocks->count());
        $this->assertEquals('temporary', $temporaryBlocks->first()->block_type);
    }

    public function test_expired_scope_returns_only_expired_blocks(): void
    {
        IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'temporary',
            'expires_at' => now()->subDay(),
            'reason' => 'Expired',
            'is_active' => true,
        ]);

        IpBlock::create([
            'ip_address' => '192.168.1.101',
            'type' => 'single',
            'block_type' => 'temporary',
            'expires_at' => now()->addDay(),
            'reason' => 'Not expired',
            'is_active' => true,
        ]);

        $expiredBlocks = IpBlock::expired()->get();

        $this->assertEquals(1, $expiredBlocks->count());
        $this->assertEquals('192.168.1.100', $expiredBlocks->first()->ip_address);
    }

    public function test_it_detects_blocked_single_ip(): void
    {
        IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Test',
            'is_active' => true,
        ]);

        $this->assertTrue(IpBlock::isIpBlocked('192.168.1.100'));
        $this->assertFalse(IpBlock::isIpBlocked('192.168.1.101'));
    }

    public function test_it_detects_blocked_ip_in_range(): void
    {
        IpBlock::create([
            'ip_address' => '192.168.1.0-255',
            'type' => 'range',
            'ip_range_start' => '192.168.1.1',
            'ip_range_end' => '192.168.1.255',
            'block_type' => 'permanent',
            'reason' => 'Test range',
            'is_active' => true,
        ]);

        Cache::flush();

        $this->assertTrue(IpBlock::isIpBlocked('192.168.1.50'));
        $this->assertTrue(IpBlock::isIpBlocked('192.168.1.100'));
        $this->assertTrue(IpBlock::isIpBlocked('192.168.1.255'));
        $this->assertFalse(IpBlock::isIpBlocked('192.168.2.1'));
    }

    public function test_it_detects_blocked_ip_by_pattern(): void
    {
        IpBlock::create([
            'ip_address' => '192.168.*.*',
            'type' => 'pattern',
            'block_type' => 'permanent',
            'reason' => 'Test pattern',
            'is_active' => true,
        ]);

        Cache::flush();

        $this->assertTrue(IpBlock::isIpBlocked('192.168.1.1'));
        $this->assertTrue(IpBlock::isIpBlocked('192.168.50.100'));
        $this->assertTrue(IpBlock::isIpBlocked('192.168.255.255'));
        $this->assertFalse(IpBlock::isIpBlocked('10.0.0.1'));
    }

    public function test_it_gets_block_for_ip(): void
    {
        $block = IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Test',
            'is_active' => true,
        ]);

        $foundBlock = IpBlock::getBlockForIp('192.168.1.100');

        $this->assertInstanceOf(IpBlock::class, $foundBlock);
        $this->assertEquals($block->id, $foundBlock->id);
    }

    public function test_it_returns_null_for_non_blocked_ip(): void
    {
        $foundBlock = IpBlock::getBlockForIp('10.0.0.1');

        $this->assertNull($foundBlock);
    }

    public function test_it_deactivates_expired_blocks(): void
    {
        IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'temporary',
            'expires_at' => now()->subDay(),
            'reason' => 'Expired',
            'is_active' => true,
        ]);

        IpBlock::create([
            'ip_address' => '192.168.1.101',
            'type' => 'single',
            'block_type' => 'temporary',
            'expires_at' => now()->addDay(),
            'reason' => 'Not expired',
            'is_active' => true,
        ]);

        $deactivated = IpBlock::deactivateExpired();

        $this->assertEquals(1, $deactivated);
        $this->assertEquals(1, IpBlock::where('is_active', false)->count());
        $this->assertEquals(1, IpBlock::where('is_active', true)->count());
    }

    public function test_it_gets_most_blocked_ips(): void
    {
        IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Test',
            'is_active' => true,
            'hit_count' => 100,
        ]);

        IpBlock::create([
            'ip_address' => '192.168.1.101',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Test',
            'is_active' => true,
            'hit_count' => 50,
        ]);

        IpBlock::create([
            'ip_address' => '192.168.1.102',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Test',
            'is_active' => true,
            'hit_count' => 200,
        ]);

        $mostBlocked = IpBlock::getMostBlockedIps(2);

        $this->assertEquals(2, $mostBlocked->count());
        $this->assertEquals('192.168.1.102', $mostBlocked->first()->ip_address);
        $this->assertEquals(200, $mostBlocked->first()->hit_count);
    }

    public function test_recent_scope_returns_blocks_within_timeframe(): void
    {
        $recent = IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Recent',
            'is_active' => true,
        ]);
        $recent->created_at = now()->subHours(12);
        $recent->save();

        $old = IpBlock::create([
            'ip_address' => '192.168.1.101',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Old',
            'is_active' => true,
        ]);
        $old->created_at = now()->subHours(48);
        $old->save();

        $recentBlocks = IpBlock::recent(24)->get();

        $this->assertEquals(1, $recentBlocks->count());
        $this->assertEquals('192.168.1.100', $recentBlocks->first()->ip_address);
    }

    public function test_it_stores_metadata(): void
    {
        $block = IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Test',
            'is_active' => true,
            'metadata' => [
                'country' => 'US',
                'user_agent' => 'Mozilla/5.0',
                'detected_by' => 'abuse_monitor',
            ],
        ]);

        $this->assertIsArray($block->metadata);
        $this->assertEquals('US', $block->metadata['country']);
        $this->assertEquals('Mozilla/5.0', $block->metadata['user_agent']);
    }

    public function test_it_casts_expires_at_to_datetime(): void
    {
        $expiresAt = now()->addDays(7);

        $block = IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'temporary',
            'expires_at' => $expiresAt,
            'reason' => 'Test',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $block->expires_at);
    }

    public function test_it_casts_is_active_to_boolean(): void
    {
        $block = IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Test',
            'is_active' => '1',
        ]);

        $this->assertIsBool($block->is_active);
        $this->assertTrue($block->is_active);
    }

    public function test_it_has_timestamps(): void
    {
        $block = IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Test',
            'is_active' => true,
        ]);

        $this->assertNotNull($block->created_at);
        $this->assertNotNull($block->updated_at);
    }

    public function test_it_caches_ip_block_check(): void
    {
        IpBlock::create([
            'ip_address' => '192.168.1.100',
            'type' => 'single',
            'block_type' => 'permanent',
            'reason' => 'Test',
            'is_active' => true,
        ]);

        // First call - should query database
        $this->assertTrue(IpBlock::isIpBlocked('192.168.1.100'));

        // Second call - should use cache (with tags)
        $cached = Cache::tags(['security'])->has('ip_block_192.168.1.100');
        $this->assertTrue($cached);
    }

    public function test_it_clears_ip_cache(): void
    {
        Cache::put('ip_block_192.168.1.100', true, 300);

        IpBlock::clearIpCache('192.168.1.100');

        $this->assertFalse(Cache::has('ip_block_192.168.1.100'));
    }
}
