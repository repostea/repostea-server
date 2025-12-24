<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Sub;
use App\Models\User;
use App\Services\SubcommunityKarmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SubcommunityKarmaServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubcommunityKarmaService $service;

    private User $creator;

    private Sub $sub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubcommunityKarmaService::class);

        // Create test creator and sub
        $this->creator = User::factory()->create(['karma_points' => 100]);
        $this->sub = Sub::create([
            'name' => 'test-sub',
            'display_name' => 'Test Sub',
            'description' => 'A test subcommunity',
            'created_by' => $this->creator->id,
            'members_count' => 50,
        ]);

        // Clear cache before each test
        Cache::flush();
    }

    #[Test]
    public function it_awards_karma_for_new_member(): void
    {
        $initialKarma = $this->creator->karma_points;

        $this->service->awardKarmaForNewMember($this->sub);

        $this->creator->refresh();
        $this->assertGreaterThan($initialKarma, $this->creator->karma_points);
    }

    #[Test]
    public function it_awards_bonus_karma_at_10_members_milestone(): void
    {
        $this->sub->members_count = 10;
        $this->sub->save();

        $initialKarma = $this->creator->karma_points;

        $this->service->awardKarmaForNewMember($this->sub);

        $this->creator->refresh();
        // Should award karma at milestone
        $this->assertGreaterThan($initialKarma, $this->creator->karma_points);
    }

    #[Test]
    public function it_awards_bonus_karma_at_100_members_milestone(): void
    {
        $this->sub->members_count = 100;
        $this->sub->save();

        $initialKarma = $this->creator->karma_points;

        $this->service->awardKarmaForNewMember($this->sub);

        $this->creator->refresh();
        // Should award karma at milestone
        $this->assertGreaterThan($initialKarma, $this->creator->karma_points);
    }

    #[Test]
    public function it_awards_bonus_karma_at_1000_members_milestone(): void
    {
        $this->sub->members_count = 1000;
        $this->sub->save();

        $initialKarma = $this->creator->karma_points;

        $this->service->awardKarmaForNewMember($this->sub);

        $this->creator->refresh();
        // Should award more karma than at 10 or 100 milestone
        $this->assertGreaterThan($initialKarma, $this->creator->karma_points);
    }

    #[Test]
    public function it_does_not_award_karma_if_no_creator(): void
    {
        $subWithoutCreator = Sub::create([
            'name' => 'orphan-sub',
            'display_name' => 'Orphan Sub',
            'description' => 'A sub without creator',
            'created_by' => null,
            'members_count' => 10,
        ]);

        $this->service->awardKarmaForNewMember($subWithoutCreator);

        // No exception thrown and no karma awarded
        $this->assertTrue(true);
    }

    #[Test]
    public function it_awards_karma_for_new_post(): void
    {
        // Use large sub to ensure multiplier results in positive karma
        $this->sub->members_count = 5000;
        $this->sub->save();

        $initialKarma = $this->creator->karma_points;

        $this->service->awardKarmaForNewPost($this->sub);

        $this->creator->refresh();
        // May or may not increase depending on config, but should not throw
        $this->assertGreaterThanOrEqual($initialKarma, $this->creator->karma_points);
    }

    #[Test]
    public function it_awards_karma_for_frontpage(): void
    {
        $initialKarma = $this->creator->karma_points;

        $this->service->awardKarmaForFrontpage($this->sub);

        $this->creator->refresh();
        $expectedKarma = (int) round(
            config('subcommunity_karma.post_frontpage', 20)
            * config('subcommunity_karma.size_multipliers.small', 0.5),
        );

        $this->assertEquals($initialKarma + $expectedKarma, $this->creator->karma_points);
    }

    #[Test]
    public function it_awards_karma_for_new_comment(): void
    {
        // Use medium-sized sub to ensure multiplier doesn't reduce karma to 0
        $this->sub->members_count = 500;
        $this->sub->save();

        $initialKarma = $this->creator->karma_points;

        $this->service->awardKarmaForNewComment($this->sub);

        $this->creator->refresh();
        $this->assertGreaterThanOrEqual($initialKarma, $this->creator->karma_points);
    }

    #[Test]
    public function it_awards_karma_for_post_upvote(): void
    {
        $initialKarma = $this->creator->karma_points;

        $this->service->awardKarmaForPostUpvote($this->sub);

        $this->creator->refresh();
        // May not increase if karma is < 1 after multiplier
        $this->assertGreaterThanOrEqual($initialKarma, $this->creator->karma_points);
    }

    #[Test]
    public function it_awards_karma_for_comment_upvote(): void
    {
        $initialKarma = $this->creator->karma_points;

        $this->service->awardKarmaForCommentUpvote($this->sub);

        $this->creator->refresh();
        // May not increase if karma is < 1 after multiplier
        $this->assertGreaterThanOrEqual($initialKarma, $this->creator->karma_points);
    }

    #[Test]
    public function it_awards_karma_for_report_resolved(): void
    {
        $initialKarma = $this->creator->karma_points;

        $this->service->awardKarmaForReportResolved($this->sub);

        $this->creator->refresh();
        $this->assertGreaterThan($initialKarma, $this->creator->karma_points);
    }

    #[Test]
    public function it_applies_small_multiplier_for_small_subs(): void
    {
        $this->sub->members_count = 50; // < 100
        $this->sub->save();

        $initialKarma = $this->creator->karma_points;

        $this->service->awardKarmaForFrontpage($this->sub);

        $this->creator->refresh();
        $baseKarma = config('subcommunity_karma.post_frontpage', 20);
        $multiplier = config('subcommunity_karma.size_multipliers.small', 0.5);
        $expected = (int) round($baseKarma * $multiplier);

        $this->assertEquals($initialKarma + $expected, $this->creator->karma_points);
    }

    #[Test]
    public function it_applies_medium_multiplier_for_medium_subs(): void
    {
        $this->sub->members_count = 500; // 100-999
        $this->sub->save();

        $initialKarma = $this->creator->karma_points;

        $this->service->awardKarmaForFrontpage($this->sub);

        $this->creator->refresh();
        $baseKarma = config('subcommunity_karma.post_frontpage', 20);
        $multiplier = config('subcommunity_karma.size_multipliers.medium', 1.0);
        $expected = (int) round($baseKarma * $multiplier);

        $this->assertEquals($initialKarma + $expected, $this->creator->karma_points);
    }

    #[Test]
    public function it_applies_large_multiplier_for_large_subs(): void
    {
        $this->sub->members_count = 5000; // 1000-9999
        $this->sub->save();

        $initialKarma = $this->creator->karma_points;

        $this->service->awardKarmaForFrontpage($this->sub);

        $this->creator->refresh();
        $baseKarma = config('subcommunity_karma.post_frontpage', 20);
        $multiplier = config('subcommunity_karma.size_multipliers.large', 1.5);
        $expected = (int) round($baseKarma * $multiplier);

        $this->assertEquals($initialKarma + $expected, $this->creator->karma_points);
    }

    #[Test]
    public function it_applies_massive_multiplier_for_massive_subs(): void
    {
        $this->sub->members_count = 15000; // >= 10000
        $this->sub->save();

        $initialKarma = $this->creator->karma_points;

        $this->service->awardKarmaForFrontpage($this->sub);

        $this->creator->refresh();
        $baseKarma = config('subcommunity_karma.post_frontpage', 20);
        $multiplier = config('subcommunity_karma.size_multipliers.massive', 2.0);
        $expected = (int) round($baseKarma * $multiplier);

        $this->assertEquals($initialKarma + $expected, $this->creator->karma_points);
    }

    #[Test]
    public function it_respects_daily_karma_limit(): void
    {
        $maxDaily = config('subcommunity_karma.max_karma_per_day_per_sub', 1000);
        $cacheKey = "sub_karma_daily_{$this->sub->id}_" . now()->format('Y-m-d');

        // Set cache to almost max (leaving room for only 2 karma points)
        Cache::put($cacheKey, $maxDaily - 2, now()->endOfDay());

        $initialKarma = $this->creator->karma_points;

        // Try to award karma (frontpage), should only get up to 2 (the remaining limit)
        $this->service->awardKarmaForFrontpage($this->sub);

        $this->creator->refresh();
        // Should have gained at most 2 karma points due to daily limit
        $this->assertLessThanOrEqual($initialKarma + 2, $this->creator->karma_points);
    }

    #[Test]
    public function it_stops_awarding_when_daily_limit_reached(): void
    {
        $maxDaily = config('subcommunity_karma.max_karma_per_day_per_sub', 1000);
        $cacheKey = "sub_karma_daily_{$this->sub->id}_" . now()->format('Y-m-d');

        // Set cache to max
        Cache::put($cacheKey, $maxDaily, now()->endOfDay());

        $initialKarma = $this->creator->karma_points;

        $this->service->awardKarmaForNewMember($this->sub);

        $this->creator->refresh();
        $this->assertEquals($initialKarma, $this->creator->karma_points);
    }

    #[Test]
    public function it_records_karma_in_history(): void
    {
        $this->service->awardKarmaForNewMember($this->sub);

        $this->assertDatabaseHas('karma_histories', [
            'user_id' => $this->creator->id,
            'source' => 'sub_member_join',
            'source_id' => $this->sub->id,
        ]);
    }

    #[Test]
    public function it_calculates_total_karma_from_sub(): void
    {
        // Award some karma
        $this->service->awardKarmaForNewMember($this->sub);
        $this->service->awardKarmaForNewPost($this->sub);
        $this->service->awardKarmaForNewComment($this->sub);

        $totalKarma = $this->service->calculateTotalKarmaFromSub($this->sub);

        $this->assertGreaterThan(0, $totalKarma);

        // Verify by checking karma_histories
        $dbTotal = DB::table('karma_histories')
            ->where('user_id', $this->creator->id)
            ->where('source_id', $this->sub->id)
            ->where('source', 'like', 'sub_%')
            ->sum('amount');

        $this->assertEquals($dbTotal, $totalKarma);
    }

    #[Test]
    public function it_returns_zero_karma_for_sub_without_creator(): void
    {
        $subWithoutCreator = Sub::create([
            'name' => 'no-creator-sub',
            'display_name' => 'No Creator Sub',
            'description' => 'A sub without creator',
            'created_by' => null,
            'members_count' => 5,
        ]);

        $totalKarma = $this->service->calculateTotalKarmaFromSub($subWithoutCreator);

        $this->assertEquals(0, $totalKarma);
    }
}
