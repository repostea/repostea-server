<?php

declare(strict_types=1);

namespace App\Console\Commands;

use const FILTER_VALIDATE_EMAIL;

use App\Models\Achievement;
use App\Models\KarmaLevel;
use App\Models\User;
use App\Notifications\AchievementUnlocked;
use App\Notifications\KarmaLevelUp;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

final class SendAllNotificationsToAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-all-to-admin
                            {email : Admin email address to send notifications to}
                            {--type= : Filter by notification type (achievement_unlocked, karma_level_up)}
                            {--user= : Send only notifications for a specific user ID}
                            {--limit=100 : Maximum number of notifications to send}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send all existing notifications from database to admin email for review';

    private int $sentCount = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $adminEmail = $this->argument('email');
        $type = $this->option('type');
        $userId = $this->option('user');
        $limit = (int) $this->option('limit');

        if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address format');

            return self::FAILURE;
        }

        $this->info("📧 Sending notifications to admin: {$adminEmail}");
        $this->newLine();

        // Create temporary admin user to receive emails
        $adminUser = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'username' => 'admin_' . substr(md5($adminEmail), 0, 8),
                'password' => bcrypt('unused'),
                'locale' => 'es',
                'email_verified_at' => now(),
                'status' => 'approved',
                'settings' => ['email_notifications' => true],
            ],
        );

        // Ensure email notifications are enabled
        $adminUser->settings = array_merge($adminUser->settings ?? [], ['email_notifications' => true]);
        $adminUser->save();

        try {
            // Get notifications from database
            $query = DatabaseNotification::whereIn('type', ['achievement_unlocked', 'karma_level_up'])
                ->orderBy('created_at', 'desc');

            if ($type) {
                $query->where('type', $type);
            }

            if ($userId) {
                $query->where('user_id', $userId);
            }

            $notifications = $query->limit($limit)->get();

            if ($notifications->isEmpty()) {
                $this->warn('No notifications found in database');

                return self::SUCCESS;
            }

            $this->info("Found {$notifications->count()} notifications");
            $this->newLine();

            $bar = $this->output->createProgressBar($notifications->count());
            $bar->start();

            foreach ($notifications as $notification) {
                $this->sendNotification($adminUser, $notification);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("✅ Sent {$this->sentCount} notifications to {$adminEmail}");
            $this->info('📬 Check your inbox!');
            $this->info('💡 In development, check Mailpit at: http://localhost:8025');

            return self::SUCCESS;
        } finally {
            // Clean up temporary admin user if it was just created
            if ($adminUser->wasRecentlyCreated) {
                $adminUser->forceDelete();
            }
        }
    }

    /**
     * Send a notification to admin.
     */
    private function sendNotification(User $adminUser, DatabaseNotification $notification): void
    {
        try {
            $data = $notification->data;
            $originalUser = User::find($notification->user_id);

            if (! $originalUser) {
                return;
            }

            // Temporarily override admin user's username and locale for context
            $originalUsername = $adminUser->username;
            $originalLocale = $adminUser->locale;

            $adminUser->username = $originalUser->username . ' (User #' . $originalUser->id . ')';
            $adminUser->locale = $originalUser->locale ?? 'es';

            // Send notification based on type
            if ($notification->type === 'achievement_unlocked') {
                $achievementId = $data['achievement_id'] ?? null;
                $achievement = Achievement::find($achievementId);

                if ($achievement) {
                    $adminUser->notify(new AchievementUnlocked($achievement));
                    $this->sentCount++;
                }
            } elseif ($notification->type === 'karma_level_up') {
                $levelId = $data['level_id'] ?? null;
                $level = KarmaLevel::find($levelId);

                if ($level) {
                    $adminUser->notify(new KarmaLevelUp($level));
                    $this->sentCount++;
                }
            }

            // Restore admin user's original data
            $adminUser->username = $originalUsername;
            $adminUser->locale = $originalLocale;
        } catch (Exception $e) {
            // Silently skip failed notifications
        }
    }
}
