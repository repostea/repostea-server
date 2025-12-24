<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Comment;
use App\Models\KarmaLevel;
use App\Models\Post;
use App\Models\SealMark;
use App\Models\User;
use App\Models\UserSeal;
use App\Services\SealService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SealServiceTest extends TestCase
{
    use RefreshDatabase;

    private SealService $sealService;

    private User $user;

    private User $contentOwner;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sealService = app(SealService::class);

        // Seed karma levels for tests
        $this->seedKarmaLevels();

        // Create test users
        $this->user = User::factory()->create();
        $this->contentOwner = User::factory()->create();

        // Create a post owned by contentOwner
        $this->post = Post::factory()->create(['user_id' => $this->contentOwner->id]);
    }

    private function seedKarmaLevels(): void
    {
        $levels = [
            ['id' => 1, 'name' => 'Novato', 'required_karma' => 0],
            ['id' => 2, 'name' => 'Aprendiz', 'required_karma' => 200],
            ['id' => 3, 'name' => 'Colaborador', 'required_karma' => 1000],
            ['id' => 4, 'name' => 'Experto', 'required_karma' => 4000],
            ['id' => 5, 'name' => 'Mentor', 'required_karma' => 16000],
            ['id' => 6, 'name' => 'Sabio', 'required_karma' => 40000],
            ['id' => 7, 'name' => 'Leyenda', 'required_karma' => 100000],
        ];

        foreach ($levels as $level) {
            KarmaLevel::create($level);
        }
    }

    #[Test]
    public function it_creates_user_seals_record_if_not_exists(): void
    {
        $userSeals = $this->sealService->getUserSeals($this->user);

        $this->assertInstanceOf(UserSeal::class, $userSeals);
        $this->assertEquals($this->user->id, $userSeals->user_id);
        $this->assertEquals(0, $userSeals->available_seals);
        $this->assertEquals(0, $userSeals->total_earned);
        $this->assertEquals(0, $userSeals->total_used);
    }

    #[Test]
    public function it_returns_existing_user_seals_record(): void
    {
        $existingSeals = UserSeal::create([
            'user_id' => $this->user->id,
            'available_seals' => 5,
            'total_earned' => 10,
            'total_used' => 5,
        ]);

        $userSeals = $this->sealService->getUserSeals($this->user);

        $this->assertEquals($existingSeals->id, $userSeals->id);
        $this->assertEquals(5, $userSeals->available_seals);
    }

    #[Test]
    public function it_applies_recommended_seal_mark_to_post(): void
    {
        // Give user some seals
        UserSeal::create([
            'user_id' => $this->user->id,
            'available_seals' => 5,
            'total_earned' => 5,
            'total_used' => 0,
        ]);

        $result = $this->sealService->applySealMark(
            $this->user,
            $this->post,
            SealMark::TYPE_RECOMMENDED,
        );

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(SealMark::class, $result['seal_mark']);
        $this->assertEquals(4, $result['available_seals']);

        // Check the seal mark was created
        $this->assertDatabaseHas('seal_marks', [
            'user_id' => $this->user->id,
            'markable_id' => $this->post->id,
            'markable_type' => Post::class,
            'type' => SealMark::TYPE_RECOMMENDED,
        ]);
    }

    #[Test]
    public function it_applies_advise_against_seal_mark_to_post(): void
    {
        UserSeal::create([
            'user_id' => $this->user->id,
            'available_seals' => 5,
            'total_earned' => 5,
            'total_used' => 0,
        ]);

        $result = $this->sealService->applySealMark(
            $this->user,
            $this->post,
            SealMark::TYPE_ADVISE_AGAINST,
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(SealMark::TYPE_ADVISE_AGAINST, $result['seal_mark']->type);
    }

    #[Test]
    public function it_throws_exception_for_invalid_seal_type(): void
    {
        UserSeal::create([
            'user_id' => $this->user->id,
            'available_seals' => 5,
            'total_earned' => 5,
            'total_used' => 0,
        ]);

        $this->expectException(Exception::class);

        $this->sealService->applySealMark($this->user, $this->post, 'invalid_type');
    }

    #[Test]
    public function it_throws_exception_when_user_has_no_seals(): void
    {
        // User has no seals (default: 0)
        $this->sealService->getUserSeals($this->user);

        $this->expectException(Exception::class);

        $this->sealService->applySealMark(
            $this->user,
            $this->post,
            SealMark::TYPE_RECOMMENDED,
        );
    }

    #[Test]
    public function it_prevents_marking_own_content(): void
    {
        $ownPost = Post::factory()->create(['user_id' => $this->user->id]);

        UserSeal::create([
            'user_id' => $this->user->id,
            'available_seals' => 5,
            'total_earned' => 5,
            'total_used' => 0,
        ]);

        $this->expectException(Exception::class);

        $this->sealService->applySealMark(
            $this->user,
            $ownPost,
            SealMark::TYPE_RECOMMENDED,
        );
    }

    #[Test]
    public function it_prevents_duplicate_seal_marks_on_same_content(): void
    {
        UserSeal::create([
            'user_id' => $this->user->id,
            'available_seals' => 5,
            'total_earned' => 5,
            'total_used' => 0,
        ]);

        // First mark succeeds
        $this->sealService->applySealMark(
            $this->user,
            $this->post,
            SealMark::TYPE_RECOMMENDED,
        );

        // Second mark fails
        $this->expectException(Exception::class);

        $this->sealService->applySealMark(
            $this->user,
            $this->post,
            SealMark::TYPE_RECOMMENDED,
        );
    }

    #[Test]
    public function it_prevents_different_seal_types_on_same_content(): void
    {
        UserSeal::create([
            'user_id' => $this->user->id,
            'available_seals' => 5,
            'total_earned' => 5,
            'total_used' => 0,
        ]);

        // First mark succeeds
        $this->sealService->applySealMark(
            $this->user,
            $this->post,
            SealMark::TYPE_RECOMMENDED,
        );

        // Different type also fails (one seal per content)
        $this->expectException(Exception::class);

        $this->sealService->applySealMark(
            $this->user,
            $this->post,
            SealMark::TYPE_ADVISE_AGAINST,
        );
    }

    #[Test]
    public function it_removes_seal_mark_and_refunds_seal(): void
    {
        UserSeal::create([
            'user_id' => $this->user->id,
            'available_seals' => 5,
            'total_earned' => 5,
            'total_used' => 0,
        ]);

        // Apply seal first
        $this->sealService->applySealMark(
            $this->user,
            $this->post,
            SealMark::TYPE_RECOMMENDED,
        );

        // Remove seal
        $result = $this->sealService->removeSealMark(
            $this->user,
            $this->post,
            SealMark::TYPE_RECOMMENDED,
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['available_seals']); // Refunded

        // Check the seal mark was deleted
        $this->assertDatabaseMissing('seal_marks', [
            'user_id' => $this->user->id,
            'markable_id' => $this->post->id,
        ]);
    }

    #[Test]
    public function it_throws_exception_when_removing_nonexistent_seal_mark(): void
    {
        $this->expectException(Exception::class);

        $this->sealService->removeSealMark(
            $this->user,
            $this->post,
            SealMark::TYPE_RECOMMENDED,
        );
    }

    #[Test]
    public function it_updates_content_seal_counts_after_apply(): void
    {
        UserSeal::create([
            'user_id' => $this->user->id,
            'available_seals' => 5,
            'total_earned' => 5,
            'total_used' => 0,
        ]);

        $this->sealService->applySealMark(
            $this->user,
            $this->post,
            SealMark::TYPE_RECOMMENDED,
        );

        $this->post->refresh();
        $this->assertEquals(1, $this->post->recommended_seals_count);
        $this->assertEquals(0, $this->post->advise_against_seals_count);
    }

    #[Test]
    public function it_awards_weekly_seals_based_on_karma_level(): void
    {
        // User at level 3 (Colaborador) should get 2 seals
        $this->user->highest_level_id = 3;
        $this->user->save();

        $sealsAwarded = $this->sealService->awardWeeklySeals($this->user);

        $this->assertEquals(2, $sealsAwarded);

        $userSeals = UserSeal::where('user_id', $this->user->id)->first();
        $this->assertEquals(2, $userSeals->available_seals);
        $this->assertEquals(2, $userSeals->total_earned);
    }

    #[Test]
    public function it_awards_zero_seals_for_novato_level(): void
    {
        // User at level 1 (Novato) gets 0 seals
        $this->user->highest_level_id = 1;
        $this->user->save();

        $sealsAwarded = $this->sealService->awardWeeklySeals($this->user);

        $this->assertEquals(0, $sealsAwarded);
    }

    #[Test]
    public function it_gets_seal_marks_for_content(): void
    {
        $user2 = User::factory()->create();

        // Create multiple seal marks
        SealMark::create([
            'user_id' => $this->user->id,
            'markable_id' => $this->post->id,
            'markable_type' => Post::class,
            'type' => SealMark::TYPE_RECOMMENDED,
            'expires_at' => now()->addDays(30),
        ]);

        SealMark::create([
            'user_id' => $user2->id,
            'markable_id' => $this->post->id,
            'markable_type' => Post::class,
            'type' => SealMark::TYPE_ADVISE_AGAINST,
            'expires_at' => now()->addDays(30),
        ]);

        $marks = $this->sealService->getSealMarksForContent($this->post);

        $this->assertCount(1, $marks['recommended']);
        $this->assertCount(1, $marks['advise_against']);
    }

    #[Test]
    public function it_excludes_expired_marks_from_content_seal_marks(): void
    {
        // Create expired seal mark
        SealMark::create([
            'user_id' => $this->user->id,
            'markable_id' => $this->post->id,
            'markable_type' => Post::class,
            'type' => SealMark::TYPE_RECOMMENDED,
            'expires_at' => now()->subDays(1), // Expired
        ]);

        $marks = $this->sealService->getSealMarksForContent($this->post);

        $this->assertCount(0, $marks['recommended']);
    }

    #[Test]
    public function it_checks_if_user_has_marked_content(): void
    {
        SealMark::create([
            'user_id' => $this->user->id,
            'markable_id' => $this->post->id,
            'markable_type' => Post::class,
            'type' => SealMark::TYPE_RECOMMENDED,
            'expires_at' => now()->addDays(30),
        ]);

        $hasMarked = $this->sealService->hasUserMarked(
            $this->user,
            $this->post,
            SealMark::TYPE_RECOMMENDED,
        );

        $this->assertTrue($hasMarked);

        $hasMarkedAgainst = $this->sealService->hasUserMarked(
            $this->user,
            $this->post,
            SealMark::TYPE_ADVISE_AGAINST,
        );

        $this->assertFalse($hasMarkedAgainst);
    }

    #[Test]
    public function it_applies_seal_mark_to_comment(): void
    {
        $comment = Comment::factory()->create(['user_id' => $this->contentOwner->id]);

        UserSeal::create([
            'user_id' => $this->user->id,
            'available_seals' => 5,
            'total_earned' => 5,
            'total_used' => 0,
        ]);

        $result = $this->sealService->applySealMark(
            $this->user,
            $comment,
            SealMark::TYPE_RECOMMENDED,
        );

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('seal_marks', [
            'markable_id' => $comment->id,
            'markable_type' => Comment::class,
        ]);
    }

    #[Test]
    public function it_cleans_up_expired_marks(): void
    {
        // Create expired seal mark
        $expiredMark = SealMark::create([
            'user_id' => $this->user->id,
            'markable_id' => $this->post->id,
            'markable_type' => Post::class,
            'type' => SealMark::TYPE_RECOMMENDED,
            'expires_at' => now()->subDays(1),
        ]);

        // Create active seal mark
        $activeMark = SealMark::create([
            'user_id' => $this->contentOwner->id,
            'markable_id' => $this->post->id,
            'markable_type' => Post::class,
            'type' => SealMark::TYPE_ADVISE_AGAINST,
            'expires_at' => now()->addDays(30),
        ]);

        $cleanedCount = $this->sealService->cleanupExpiredMarks();

        $this->assertEquals(1, $cleanedCount);
        $this->assertDatabaseMissing('seal_marks', ['id' => $expiredMark->id]);
        $this->assertDatabaseHas('seal_marks', ['id' => $activeMark->id]);
    }
}
