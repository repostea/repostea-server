<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\TransparencyStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TransparencyStatTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_transparency_stats(): void
    {
        $stat = TransparencyStat::create([
            'total_posts' => 1000,
            'total_users' => 500,
            'total_comments' => 2000,
            'total_aggregated_sources' => 50,
            'reports_total' => 100,
            'reports_processed' => 80,
            'reports_pending' => 20,
            'avg_response_hours' => 24.5,
            'content_removed' => 15,
            'warnings_issued' => 25,
            'users_suspended' => 5,
            'appeals_total' => 10,
            'report_types' => ['spam' => 40, 'harassment' => 30, 'other' => 30],
            'calculated_at' => now(),
        ]);

        $this->assertInstanceOf(TransparencyStat::class, $stat);
        $this->assertEquals(1000, $stat->total_posts);
        $this->assertEquals(500, $stat->total_users);
        $this->assertEquals(2000, $stat->total_comments);
    }

    public function test_it_casts_report_types_to_array(): void
    {
        $stat = TransparencyStat::create([
            'total_posts' => 100,
            'total_users' => 50,
            'report_types' => ['spam' => 10, 'abuse' => 5],
            'calculated_at' => now(),
        ]);

        $this->assertIsArray($stat->report_types);
        $this->assertEquals(['spam' => 10, 'abuse' => 5], $stat->report_types);
    }

    public function test_it_casts_calculated_at_to_datetime(): void
    {
        $date = now();
        $stat = TransparencyStat::create([
            'total_posts' => 100,
            'total_users' => 50,
            'calculated_at' => $date,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $stat->calculated_at);
        $this->assertEquals($date->format('Y-m-d H:i:s'), $stat->calculated_at->format('Y-m-d H:i:s'));
    }

    public function test_it_returns_latest_stats(): void
    {
        // Create multiple stats with different dates
        TransparencyStat::create([
            'total_posts' => 100,
            'total_users' => 50,
            'calculated_at' => now()->subDays(2),
        ]);

        $latest = TransparencyStat::create([
            'total_posts' => 200,
            'total_users' => 100,
            'calculated_at' => now(),
        ]);

        TransparencyStat::create([
            'total_posts' => 150,
            'total_users' => 75,
            'calculated_at' => now()->subDay(),
        ]);

        $result = TransparencyStat::getLatest();

        $this->assertInstanceOf(TransparencyStat::class, $result);
        $this->assertEquals($latest->id, $result->id);
        $this->assertEquals(200, $result->total_posts);
    }

    public function test_it_returns_null_when_no_stats_exist(): void
    {
        $result = TransparencyStat::getLatest();

        $this->assertNull($result);
    }

    public function test_it_has_timestamps(): void
    {
        $stat = TransparencyStat::create([
            'total_posts' => 100,
            'total_users' => 50,
            'calculated_at' => now(),
        ]);

        $this->assertNotNull($stat->created_at);
        $this->assertNotNull($stat->updated_at);
    }

    public function test_it_stores_all_report_metrics(): void
    {
        $stat = TransparencyStat::create([
            'total_posts' => 1000,
            'total_users' => 500,
            'reports_total' => 100,
            'reports_processed' => 75,
            'reports_pending' => 25,
            'avg_response_hours' => 12.5,
            'content_removed' => 20,
            'warnings_issued' => 30,
            'users_suspended' => 8,
            'appeals_total' => 5,
            'calculated_at' => now(),
        ]);

        $this->assertEquals(100, $stat->reports_total);
        $this->assertEquals(75, $stat->reports_processed);
        $this->assertEquals(25, $stat->reports_pending);
        $this->assertEquals(12.5, $stat->avg_response_hours);
        $this->assertEquals(20, $stat->content_removed);
        $this->assertEquals(30, $stat->warnings_issued);
        $this->assertEquals(8, $stat->users_suspended);
        $this->assertEquals(5, $stat->appeals_total);
    }

    public function test_it_can_handle_empty_report_types(): void
    {
        $stat = TransparencyStat::create([
            'total_posts' => 100,
            'total_users' => 50,
            'calculated_at' => now(),
            'avg_response_hours' => 0,
            'report_types' => [],
        ]);

        $this->assertEquals(0, $stat->avg_response_hours);
        $this->assertEquals([], $stat->report_types);
    }
}
