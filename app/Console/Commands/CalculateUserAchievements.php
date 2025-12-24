<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Console\Command;

final class CalculateUserAchievements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'achievements:calculate
                            {--user= : Calculate achievements for a specific user ID}
                            {--all : Calculate achievements for all users}
                            {--recent=none : Calculate for users with activity in last X hours (default: 1 if flag present)}
                            {--force : Force recalculation even if already unlocked}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and unlock user achievements based on current activity';

    private int $totalUnlocked = 0;

    private int $totalProcessed = 0;

    /**
     * Create a new command instance.
     */
    public function __construct(
        private AchievementService $achievementService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Validate that at least one required option has been specified
        // --recent has default 'none', so any other value means the flag was used
        $hasRecent = $this->option('recent') !== 'none';
        if (! $this->option('user') && ! $this->option('all') && ! $hasRecent) {
            $this->error('You must specify either --user=ID, --all, or --recent=HOURS');

            return self::FAILURE;
        }

        $this->info('Starting achievement calculation...');

        // If a specific user is specified, process it directly
        if ($this->option('user')) {
            return $this->processSpecificUser();
        }

        // Get base query based on options
        $query = $this->getUsersQuery();

        // Count total for progress bar
        $total = $query->count();

        if ($total === 0) {
            $this->info('No users found to process.');

            return self::SUCCESS;
        }

        $this->info("Processing {$total} user(s)...");

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        // Process in chunks of 100 users
        $query->chunk(100, function ($users) use ($progressBar): void {
            foreach ($users as $user) {
                $this->calculateUserAchievements($user);
                $this->totalProcessed++;
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        $this->info("✅ Processed {$this->totalProcessed} user(s)");
        $this->info("🏆 Unlocked {$this->totalUnlocked} achievement(s)");

        return self::SUCCESS;
    }

    /**
     * Process a specific user.
     */
    private function processSpecificUser(): int
    {
        $userId = (int) $this->option('user');
        $user = User::find($userId);

        if ($user === null) {
            $this->error("User with ID {$userId} not found.");

            return self::FAILURE;
        }

        $this->info("Processing user #{$userId}...");
        $this->calculateUserAchievements($user);
        $this->totalProcessed++;

        $this->newLine();
        $this->info('✅ Processed 1 user');
        $this->info("🏆 Unlocked {$this->totalUnlocked} achievement(s)");

        return self::SUCCESS;
    }

    /**
     * Get the users query based on options.
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    private function getUsersQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Option --recent: users with recent activity
        // Default is 'none', empty string or numeric value means flag was used
        $recentOption = $this->option('recent');
        if ($recentOption !== 'none') {
            $hours = is_numeric($recentOption) && (int) $recentOption > 0 ? (int) $recentOption : 1;
            $since = now()->subHours($hours);

            $this->info("Filtering users with activity in the last {$hours} hour(s)...");

            return User::query()
                ->where(function ($query) use ($since): void {
                    $query->whereHas('posts', function ($q) use ($since): void {
                        $q->where('created_at', '>=', $since);
                    })
                        ->orWhereHas('comments', function ($q) use ($since): void {
                            $q->where('created_at', '>=', $since);
                        })
                        ->orWhereHas('votes', function ($q) use ($since): void {
                            $q->where('created_at', '>=', $since);
                        });
                });
        }

        // Option --all: all users (default if we get here)
        return User::query();
    }

    /**
     * Calculate achievements for a specific user.
     */
    private function calculateUserAchievements(User $user): void
    {
        // Load relationships
        $user->load(['streak', 'achievements']);

        // Registration achievement (everyone gets this)
        $this->unlockAchievement($user, 'welcome');

        // Early adopter (registered in 2025)
        if ($user->created_at->year === 2025) {
            $this->unlockAchievement($user, 'early_adopter');
        }

        // Post-based achievements
        $postsCount = $user->posts()->count();
        if ($postsCount >= 1) {
            $this->unlockAchievement($user, 'first_post');
        }
        $this->checkMilestones($user, 'posts', $postsCount, [10, 50, 100]);

        // Comment-based achievements
        $commentsCount = $user->comments()->count();
        if ($commentsCount >= 1) {
            $this->unlockAchievement($user, 'first_comment');
        }
        $this->checkMilestones($user, 'comments', $commentsCount, [10, 50, 100]);

        // Vote-based achievements
        $votesCount = $user->votes()->count();
        if ($votesCount >= 1) {
            $this->unlockAchievement($user, 'first_vote');
        }
        $this->checkMilestones($user, 'votes', $votesCount, [10, 50, 100]);
        // Also unlock active_voter at 10 votes
        if ($votesCount >= 10) {
            $this->unlockAchievement($user, 'active_voter');
        }

        // Streak-based achievements
        $streakDays = $user->streak?->current_streak ?? 0;
        $this->checkMilestones($user, 'streak', $streakDays, [7, 30, 90, 180, 365]);

        // Karma-based achievements
        $karma = $user->karma_points ?? 0;
        $this->checkMilestones($user, 'karma', $karma, [100, 500, 1000, 5000, 10000, 50000]);

        // Special achievements based on ratios and combinations
        $this->checkSpecialAchievements($user, $postsCount, $commentsCount, $votesCount, $karma);
    }

    /**
     * Check and unlock milestone achievements.
     */
    private function checkMilestones(User $user, string $type, int $count, array $milestones): void
    {
        foreach ($milestones as $milestone) {
            if ($count >= $milestone) {
                $achievementSlug = "{$type}_{$milestone}"; // Use underscore not hyphen
                $this->unlockAchievement($user, $achievementSlug);
            }
        }
    }

    /**
     * Check special achievements.
     */
    private function checkSpecialAchievements(User $user, int $postsCount, int $commentsCount, int $votesCount, int $karma): void
    {
        // Conversationalist: More comments than posts (and at least 10 comments)
        if ($commentsCount > $postsCount && $commentsCount >= 10) {
            $this->unlockAchievement($user, 'conversationalist');
        }

        // Content Creator: More posts than comments (and at least 10 posts)
        if ($postsCount > $commentsCount && $postsCount >= 10) {
            $this->unlockAchievement($user, 'content_creator');
        }

        // Active Voter: Has voted more times than posted (and at least 50 votes)
        if ($votesCount > $postsCount && $votesCount >= 50) {
            $this->unlockAchievement($user, 'active_voter');
        }

        // Community Pillar: 100+ posts, 500+ comments, 1000+ votes
        if ($postsCount >= 100 && $commentsCount >= 500 && $votesCount >= 1000) {
            $this->unlockAchievement($user, 'community_pillar');
        }

        // Karma Master: 10000+ karma with less than 100 posts (quality over quantity)
        if ($karma >= 10000 && $postsCount < 100) {
            $this->unlockAchievement($user, 'karma_master');
        }
    }

    /**
     * Unlock an achievement for a user.
     */
    private function unlockAchievement(User $user, string $achievementSlug): void
    {
        // Check if already unlocked (unless force option is set)
        if (! $this->option('force')) {
            $existing = $user->achievements()
                ->where('slug', $achievementSlug)
                ->wherePivot('unlocked_at', '!=', null)
                ->exists();

            if ($existing) {
                return;
            }
        }

        $achievement = $this->achievementService->unlockIfExists($user, $achievementSlug);

        if ($achievement !== null) {
            $this->totalUnlocked++;
        }
    }
}
