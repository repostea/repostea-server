<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Achievement;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use App\Notifications\AchievementUnlocked as AchievementUnlockedNotification;
use DateTime;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RebuildCompleteHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'history:rebuild
                            {--from= : Start date (YYYY-MM-DD), default: 1 year ago}
                            {--to= : End date (YYYY-MM-DD), default: today}
                            {--dry-run : Show what would happen without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ONE-TIME: Rebuild complete history including notifications, karma, achievements, and stats';

    private int $totalAchievements = 0;

    private int $totalNotifications = 0;

    private int $totalKarmaEntries = 0;

    private bool $dryRun = false;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->dryRun = $this->option('dry-run');

        $this->warn('⚠️  COMPLETE HISTORY REBUILD');
        $this->warn('This will DELETE and rebuild ALL:');
        $this->warn('  - Notifications');
        $this->warn('  - Karma history');
        $this->warn('  - User achievements');
        $this->warn('  - User levels');
        $this->newLine();

        if (! $this->dryRun && ! $this->confirm('Are you absolutely sure you want to proceed?', false)) {
            $this->info('Aborted.');

            return self::FAILURE;
        }

        $from = $this->option('from') ?? now()->subYear()->toDateString();
        $to = $this->option('to') ?? now()->toDateString();

        if ($this->dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info("Rebuilding complete history from {$from} to {$to}");
        $this->newLine();

        // Step 1: Clean all existing data
        $this->cleanAllData();

        // Step 2: Get all users
        $users = User::with(['posts', 'comments', 'votes'])->get();
        $this->info("Found {$users->count()} users to process");
        $this->newLine();

        // Step 3: Process day by day
        $startDate = new DateTime($from);
        $endDate = new DateTime($to);
        $totalDays = $startDate->diff($endDate)->days + 1;

        $this->info("Processing {$totalDays} days of history...");
        $dayBar = $this->output->createProgressBar($totalDays);
        $dayBar->start();

        $currentDate = clone $startDate;
        while ($currentDate <= $endDate) {
            $dateString = $currentDate->format('Y-m-d');

            foreach ($users as $user) {
                $this->processUserForDate($user, $dateString);
            }

            $currentDate->modify('+1 day');
            $dayBar->advance();
        }

        $dayBar->finish();
        $this->newLine(2);

        // Step 4: Populate daily stats
        if (! $this->dryRun) {
            $this->info('📊 Populating daily karma stats...');
            $this->call('karma:populate-daily-stats', [
                '--truncate' => true,
                '--from' => $from,
            ]);
        }

        // Step 5: Clear cache
        if (! $this->dryRun) {
            $this->info('🧹 Clearing cache...');
            $this->call('cache:clear');
        }

        // Statistics
        $this->newLine();
        $this->info('✅ Complete rebuild finished!');
        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total users processed', $users->count()],
                ['Total days processed', $totalDays],
                ['Achievements unlocked', $this->totalAchievements],
                ['Notifications sent', $this->totalNotifications],
                ['Karma history entries', $this->totalKarmaEntries],
                ['Mode', $this->dryRun ? 'DRY RUN' : 'LIVE'],
            ],
        );

        if ($this->dryRun) {
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        } else {
            $this->info('All done! History has been completely rebuilt.');
        }

        return self::SUCCESS;
    }

    /**
     * Clean all existing data.
     */
    private function cleanAllData(): void
    {
        $this->info('🧹 Cleaning existing data...');

        if (! $this->dryRun) {
            // Delete all notifications
            $notificationsDeleted = DB::table('notifications')->delete();
            $this->line("  Deleted {$notificationsDeleted} notifications");

            // Delete all karma history
            $karmaDeleted = DB::table('karma_histories')->delete();
            $this->line("  Deleted {$karmaDeleted} karma history records");

            // Delete all user achievements
            $achievementsDeleted = DB::table('achievement_user')->delete();
            $this->line("  Deleted {$achievementsDeleted} user achievements");

            // Reset user karma and levels
            DB::table('users')->update([
                'karma_points' => 0,
                'highest_level_id' => null,
            ]);
            $this->line('  Reset all user karma and levels');
        } else {
            $notificationCount = DB::table('notifications')->count();
            $karmaCount = DB::table('karma_histories')->count();
            $achievementCount = DB::table('achievement_user')->count();
            $this->line("  Would delete {$notificationCount} notifications");
            $this->line("  Would delete {$karmaCount} karma history records");
            $this->line("  Would delete {$achievementCount} user achievements");
            $this->line('  Would reset all user karma and levels');
        }

        $this->newLine();
    }

    /**
     * Process a user for a specific date.
     */
    private function processUserForDate(User $user, string $date): void
    {
        // Only process if user existed on this date
        if ($user->created_at->format('Y-m-d') > $date) {
            return;
        }

        $dateTime = new DateTime($date . ' 23:59:59');

        // Check if this is registration date
        if ($user->created_at->format('Y-m-d') === $date) {
            $this->processRegistration($user, $dateTime);
        }

        // Get user stats UP TO this date
        $postsCountBefore = $user->posts()->where('created_at', '<', $dateTime)->count();
        $commentsCountBefore = $user->comments()->where('created_at', '<', $dateTime)->count();
        $votesCountBefore = $user->votes()->where('created_at', '<', $dateTime)->count();

        // Get actions on this specific date
        $postsToday = $user->posts()->whereDate('created_at', $date)->get();
        $commentsToday = $user->comments()->whereDate('created_at', $date)->get();
        $votesToday = $user->votes()->whereDate('created_at', $date)->get();

        // Process each action with karma
        foreach ($postsToday as $post) {
            $this->processPostCreated($user, $post);
        }

        foreach ($commentsToday as $comment) {
            $this->processCommentCreated($user, $comment);
        }

        foreach ($votesToday as $vote) {
            $this->processVoteCreated($user, $vote);
        }

        // Get current counts (including today)
        $postsCount = $postsCountBefore + $postsToday->count();
        $commentsCount = $commentsCountBefore + $commentsToday->count();
        $votesCount = $votesCountBefore + $votesToday->count();

        // Get karma UP TO this date (from database)
        $karma = (int) DB::table('karma_histories')
            ->where('user_id', $user->id)
            ->where('created_at', '<=', $dateTime)
            ->sum('amount');

        // Check achievements for current counts
        $this->checkAchievements($user, $postsCount, $commentsCount, $votesCount, $karma, $date);

        // Update user level if needed
        if (! $this->dryRun) {
            $this->updateUserLevel($user, $karma);
        }
    }

    /**
     * Process user registration.
     */
    private function processRegistration(User $user, DateTime $date): void
    {
        // Send welcome notification
        if (! $this->dryRun) {
            // Create registration notification
            DB::table('notifications')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'App\\Notifications\\WelcomeNotification',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'title' => '¡Bienvenido a Repostea!',
                    'body' => 'Gracias por unirte a nuestra comunidad.',
                ]),
                'read_at' => null,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
            $this->totalNotifications++;
        }
    }

    /**
     * Process post creation with karma.
     */
    private function processPostCreated(User $user, Post $post): void
    {
        $karmaAmount = 10; // Default karma for creating a post

        if (! $this->dryRun) {
            DB::table('karma_histories')->insert([
                'user_id' => $user->id,
                'amount' => $karmaAmount,
                'source' => 'post',
                'source_id' => $post->id,
                'description' => 'Post created',
                'created_at' => $post->created_at,
                'updated_at' => $post->created_at,
            ]);
            $this->totalKarmaEntries++;
        }
    }

    /**
     * Process comment creation with karma.
     */
    private function processCommentCreated(User $user, Comment $comment): void
    {
        $karmaAmount = 5; // Default karma for creating a comment

        if (! $this->dryRun) {
            DB::table('karma_histories')->insert([
                'user_id' => $user->id,
                'amount' => $karmaAmount,
                'source' => 'comment',
                'source_id' => $comment->id,
                'description' => 'Comentario creado',
                'created_at' => $comment->created_at,
                'updated_at' => $comment->created_at,
            ]);
            $this->totalKarmaEntries++;
        }
    }

    /**
     * Process vote creation with karma.
     */
    private function processVoteCreated(User $user, Vote $vote): void
    {
        $karmaAmount = 1; // Default karma for voting

        if (! $this->dryRun) {
            DB::table('karma_histories')->insert([
                'user_id' => $user->id,
                'amount' => $karmaAmount,
                'source' => 'vote',
                'source_id' => $vote->id,
                'description' => 'Voto emitido',
                'created_at' => $vote->created_at,
                'updated_at' => $vote->created_at,
            ]);
            $this->totalKarmaEntries++;
        }
    }

    /**
     * Check and unlock achievements.
     */
    private function checkAchievements(User $user, int $postsCount, int $commentsCount, int $votesCount, int $karma, string $date): void
    {
        $achievements = [];

        // Welcome achievement (on registration)
        if ($user->created_at->format('Y-m-d') === $date) {
            $achievements[] = 'welcome';

            if ($user->created_at->year === 2025) {
                $achievements[] = 'early_adopter';
            }
        }

        // Post achievements
        if ($postsCount >= 1) {
            $achievements[] = 'first_post';
        }
        foreach ([10, 50, 100] as $milestone) {
            if ($postsCount >= $milestone) {
                $achievements[] = "posts_{$milestone}";
            }
        }

        // Comment achievements
        if ($commentsCount >= 1) {
            $achievements[] = 'first_comment';
        }
        foreach ([10, 50, 100] as $milestone) {
            if ($commentsCount >= $milestone) {
                $achievements[] = "comments_{$milestone}";
            }
        }

        // Vote achievements
        if ($votesCount >= 1) {
            $achievements[] = 'first_vote';
        }
        if ($votesCount >= 10) {
            $achievements[] = 'active_voter';
        }
        foreach ([10, 50, 100] as $milestone) {
            if ($votesCount >= $milestone) {
                $achievements[] = "votes_{$milestone}";
            }
        }

        // Karma achievements
        foreach ([100, 500, 1000, 5000, 10000, 50000] as $milestone) {
            if ($karma >= $milestone) {
                $achievements[] = "karma_{$milestone}";
            }
        }

        // Special achievements
        if ($commentsCount > $postsCount && $commentsCount >= 10) {
            $achievements[] = 'conversationalist';
        }
        if ($postsCount > $commentsCount && $postsCount >= 10) {
            $achievements[] = 'content_creator';
        }
        if ($postsCount >= 100 && $commentsCount >= 500 && $votesCount >= 1000) {
            $achievements[] = 'community_pillar';
        }
        if ($karma >= 10000 && $postsCount < 100) {
            $achievements[] = 'karma_master';
        }

        // Unlock all achievements
        foreach ($achievements as $slug) {
            $this->unlockAchievement($user, $slug, $date);
        }
    }

    /**
     * Unlock an achievement.
     */
    private function unlockAchievement(User $user, string $achievementSlug, string $date): void
    {
        // Check if already unlocked
        if (! $this->dryRun) {
            $existing = DB::table('achievement_user')
                ->where('user_id', $user->id)
                ->where('achievement_id', function ($query) use ($achievementSlug): void {
                    $query->select('id')
                        ->from('achievements')
                        ->where('slug', $achievementSlug)
                        ->limit(1);
                })
                ->exists();

            if ($existing) {
                return;
            }
        }

        $achievement = Achievement::where('slug', $achievementSlug)->first();

        if (! $achievement) {
            return;
        }

        if ($this->dryRun) {
            $this->totalAchievements++;

            return;
        }

        $unlockedAt = new DateTime($date . ' 23:59:59');

        // Unlock achievement
        DB::table('achievement_user')->insert([
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
            'unlocked_at' => $unlockedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->totalAchievements++;

        // Add karma history
        if ($achievement->karma_bonus > 0) {
            DB::table('karma_histories')->insert([
                'user_id' => $user->id,
                'amount' => $achievement->karma_bonus,
                'source' => 'achievement',
                'source_id' => $achievement->id,
                'description' => "Logro desbloqueado: {$achievement->slug}",
                'created_at' => $unlockedAt,
                'updated_at' => $unlockedAt,
            ]);
            $this->totalKarmaEntries++;
        }

        // Send notification with historical date
        $userModel = User::find($user->id);
        if ($userModel) {
            try {
                // Create notification instance to get proper data formatting
                $notification = new AchievementUnlockedNotification($achievement);

                // Get the formatted notification data
                $notificationData = $notification->toArray($userModel);

                // Insert notification directly with historical date
                DB::table('notifications')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'type' => 'App\\Notifications\\AchievementUnlocked',
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => $user->id,
                    'data' => json_encode($notificationData),
                    'read_at' => null,
                    'created_at' => $unlockedAt,
                    'updated_at' => $unlockedAt,
                ]);

                $this->totalNotifications++;
            } catch (Exception $e) {
                // Silently ignore notification errors
            }
        }
    }

    /**
     * Update user karma level.
     */
    private function updateUserLevel(User $user, int $karma): void
    {
        $userModel = User::find($user->id);
        if (! $userModel) {
            return;
        }

        $userModel->karma_points = $karma;
        $currentLevel = $userModel->calculateCurrentLevel();

        if ($currentLevel && ($userModel->highest_level_id === null || $currentLevel->id > $userModel->highest_level_id)) {
            $userModel->highest_level_id = $currentLevel->id;
        }

        $userModel->save();
    }
}
