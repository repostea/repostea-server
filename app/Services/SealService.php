<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\SealMark;
use App\Models\User;
use App\Models\UserSeal;
use Exception;
use Illuminate\Support\Facades\DB;

final class SealService
{
    public function __construct(
        private readonly KarmaService $karmaService,
    ) {}

    /**
     * Get or create user seals record.
     */
    public function getUserSeals(User $user): UserSeal
    {
        return UserSeal::firstOrCreate(
            ['user_id' => $user->id],
            [
                'available_seals' => 0,
                'total_earned' => 0,
                'total_used' => 0,
            ],
        );
    }

    /**
     * Apply a seal mark to content (Post or Comment).
     */
    public function applySealMark(User $user, Post|Comment $content, string $type): array
    {
        // Validate type
        if (! in_array($type, [SealMark::TYPE_RECOMMENDED, SealMark::TYPE_ADVISE_AGAINST])) {
            throw new Exception(__('seals.invalid_type'));
        }

        // Check if user is trying to mark their own content
        if ($content->user_id === $user->id) {
            throw new Exception(__('seals.cannot_mark_own_content'));
        }

        // Get user seals
        $userSeals = $this->getUserSeals($user);

        // Check if user has available seals
        if (! $userSeals->hasSeals(1)) {
            throw new Exception(__('seals.no_seals_available'));
        }

        DB::beginTransaction();

        try {
            // Check if user already marked this content with ANY type
            $existingMark = SealMark::where('user_id', $user->id)
                ->where('markable_id', $content->id)
                ->where('markable_type', get_class($content))
                ->first();

            if ($existingMark) {
                if ($existingMark->type === $type) {
                    throw new Exception(__('seals.already_marked_with_type'));
                }
                throw new Exception(__('seals.one_seal_per_content'));
            }

            // Use one seal
            $userSeals->useSeals(1);

            // Create seal mark (expires in 30 days)
            $sealMark = SealMark::create([
                'user_id' => $user->id,
                'markable_id' => $content->id,
                'markable_type' => get_class($content),
                'type' => $type,
                'expires_at' => now()->addDays(30),
            ]);

            // Update content seal counts
            $this->updateContentSealCounts($content);

            // Award/remove karma to content owner
            $contentOwner = $content->user;
            if ($contentOwner) {
                $karmaAmount = $type === SealMark::TYPE_RECOMMENDED ? 3 : -2;
                $description = $type === SealMark::TYPE_RECOMMENDED
                    ? 'Received recommended seal on ' . ($content instanceof Post ? 'post' : 'comment') . " #{$content->id}"
                    : 'Received advise against seal on ' . ($content instanceof Post ? 'post' : 'comment') . " #{$content->id}";

                $this->karmaService->addKarma(
                    $contentOwner,
                    $karmaAmount,
                    'seal',
                    $sealMark->id,
                    $description,
                );
            }

            DB::commit();

            return [
                'success' => true,
                'seal_mark' => $sealMark,
                'available_seals' => $userSeals->available_seals,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Remove a seal mark from content.
     */
    public function removeSealMark(User $user, Post|Comment $content, string $type): array
    {
        DB::beginTransaction();

        try {
            // Find the seal mark
            $sealMark = SealMark::where('user_id', $user->id)
                ->where('markable_id', $content->id)
                ->where('markable_type', get_class($content))
                ->where('type', $type)
                ->first();

            if (! $sealMark) {
                throw new Exception(__('seals.mark_not_found'));
            }

            // Store seal type and mark id before deletion
            $sealType = $sealMark->type;
            $sealMarkId = $sealMark->id;

            // Delete seal mark
            $sealMark->delete();

            // Refund seal to user
            $userSeals = $this->getUserSeals($user);
            $userSeals->available_seals += 1;
            $userSeals->total_used -= 1;
            $userSeals->save();

            // Update content seal counts
            $this->updateContentSealCounts($content);

            // Reverse karma from content owner
            $contentOwner = $content->user;
            if ($contentOwner) {
                $karmaAmount = $sealType === SealMark::TYPE_RECOMMENDED ? -3 : 2;
                $description = $sealType === SealMark::TYPE_RECOMMENDED
                    ? 'Removed recommended seal from ' . ($content instanceof Post ? 'post' : 'comment') . " #{$content->id}"
                    : 'Removed advise against seal from ' . ($content instanceof Post ? 'post' : 'comment') . " #{$content->id}";

                $this->karmaService->addKarma(
                    $contentOwner,
                    $karmaAmount,
                    'seal_removed',
                    $sealMarkId,
                    $description,
                );
            }

            DB::commit();

            return [
                'success' => true,
                'available_seals' => $userSeals->available_seals,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update seal counts on content.
     */
    protected function updateContentSealCounts($content): void
    {
        $recommendedCount = SealMark::where('markable_id', $content->id)
            ->where('markable_type', get_class($content))
            ->where('type', SealMark::TYPE_RECOMMENDED)
            ->active()
            ->count();

        $adviseAgainstCount = SealMark::where('markable_id', $content->id)
            ->where('markable_type', get_class($content))
            ->where('type', SealMark::TYPE_ADVISE_AGAINST)
            ->active()
            ->count();

        $content->recommended_seals_count = $recommendedCount;
        $content->advise_against_seals_count = $adviseAgainstCount;
        $content->save();
    }

    /**
     * Award weekly seals to a user based on their karma level.
     */
    public function awardWeeklySeals(User $user): int
    {
        $sealsToAward = $this->getSealsPerWeekForKarmaLevel($user->highest_level_id);

        if ($sealsToAward <= 0) {
            return 0;
        }

        $userSeals = $this->getUserSeals($user);

        // Remove expired seals first
        $userSeals->removeExpiredSeals();

        // Award new seals
        $userSeals->awardSeals($sealsToAward);

        return $sealsToAward;
    }

    /**
     * Get seals per week based on karma level.
     */
    protected function getSealsPerWeekForKarmaLevel(?int $karmaLevelId): int
    {
        // Map karma level ID to seals per week
        // Simple progression: 1 seal per level (0, 1, 2, 3, 4, 5, 6)
        $sealsMap = [
            1 => 0,  // Novato
            2 => 1,  // Aprendiz
            3 => 2,  // Colaborador
            4 => 3,  // Experto
            5 => 4,  // Mentor
            6 => 5,  // Sabio
            7 => 6,  // Leyenda
        ];

        return $sealsMap[$karmaLevelId] ?? 0;
    }

    /**
     * Clean up expired seal marks.
     */
    public function cleanupExpiredMarks(): int
    {
        $expiredMarks = SealMark::expired()->get();

        foreach ($expiredMarks as $mark) {
            $mark->delete();

            // Update content seal counts
            if ($mark->markable) {
                $this->updateContentSealCounts($mark->markable);
            }
        }

        return $expiredMarks->count();
    }

    /**
     * Get seal marks for content.
     */
    public function getSealMarksForContent($content): array
    {
        if (! ($content instanceof Post) && ! ($content instanceof Comment)) {
            return [];
        }

        $marks = SealMark::where('markable_id', $content->id)
            ->where('markable_type', get_class($content))
            ->active()
            ->with('user')
            ->get();

        return [
            'recommended' => $marks->where('type', SealMark::TYPE_RECOMMENDED)->values(),
            'advise_against' => $marks->where('type', SealMark::TYPE_ADVISE_AGAINST)->values(),
        ];
    }

    /**
     * Check if user has marked content with specific type.
     */
    public function hasUserMarked(User $user, $content, string $type): bool
    {
        if (! ($content instanceof Post) && ! ($content instanceof Comment)) {
            return false;
        }

        return SealMark::where('user_id', $user->id)
            ->where('markable_id', $content->id)
            ->where('markable_type', get_class($content))
            ->where('type', $type)
            ->active()
            ->exists();
    }
}
