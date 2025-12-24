<?php

declare(strict_types=1);

namespace App\Console\Commands;

use const FILTER_VALIDATE_EMAIL;

use App\Mail\LegalReportResolutionMail;
use App\Models\Achievement;
use App\Models\KarmaEvent;
use App\Models\KarmaLevel;
use App\Models\LegalReport;
use App\Models\User;
use App\Notifications\AccountApprovedNotification;
use App\Notifications\AccountRejectedNotification;
use App\Notifications\AchievementUnlocked;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\KarmaEventStarting;
use App\Notifications\KarmaLevelUp;
use App\Notifications\MagicLinkLogin;
use App\Notifications\NewUserRegistrationNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class TestEmailsCommand extends Command
{
    protected $signature = 'emails:test {email : Email address to send test emails to}';

    protected $description = 'Send all system email notifications to a test email address in both Spanish and English';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address format');

            return Command::FAILURE;
        }

        $this->info("Sending test emails to: {$email}");
        $this->newLine();

        // Clean up any existing test users first to avoid conflicts
        User::where('email', 'LIKE', 'test_%@test-emails.local')->forceDelete();

        // Create temporary test users with Spanish and English locales
        $userEs = $this->createTestUser('es');
        $userEn = $this->createTestUser('en');

        try {
            // Spanish emails
            $this->info('📧 Sending emails in Spanish...');
            $this->sendAllEmails($userEs, 'es');

            $this->newLine();

            // English emails
            $this->info('📧 Sending emails in English...');
            $this->sendAllEmails($userEn, 'en');

            $this->newLine();
            $this->info('✅ All test emails sent successfully!');
            $this->info("📬 Check your inbox at: {$email}");
            $this->info('💡 In development, check Mailpit at: http://localhost:8025');

            return Command::SUCCESS;
        } finally {
            // Clean up test users
            $userEs->forceDelete();
            $userEn->forceDelete();
        }
    }

    private function createTestUser(string $locale): User
    {
        // Create a unique temporary email that doesn't conflict with existing users
        $uniqueEmail = 'test_' . $locale . '_' . Str::random(10) . '@test-emails.local';

        return User::create([
            'username' => 'test_' . $locale . '_' . Str::random(8),
            'email' => $uniqueEmail,
            'password' => Hash::make(Str::random(32)),
            'locale' => $locale,
            'email_verified_at' => now(),
            'status' => 'approved',
        ]);
    }

    private function sendAllEmails(User $user, string $locale): void
    {
        $lang = $locale === 'es' ? '🇪🇸' : '🇬🇧';

        // Get the target email address from command argument
        $targetEmail = $this->argument('email');

        // Temporarily override the user's email to send to the target address
        $originalEmail = $user->email;
        $user->email = $targetEmail;

        // 1. Email Verification
        $this->line("  {$lang} Sending: Email Verification");
        $user->notify(new EmailVerificationNotification());

        // 2. Password Reset
        $this->line("  {$lang} Sending: Password Reset");
        $user->notify(new ResetPasswordNotification('test-token-' . Str::random(40)));

        // 3. Magic Link
        $this->line("  {$lang} Sending: Magic Link Login");
        $magicLinkUrl = config('app.client_url') . "/{$locale}/auth/magic-link?token=test123";
        $user->notify(new MagicLinkLogin($magicLinkUrl));

        // 4. Account Approved
        $this->line("  {$lang} Sending: Account Approved");
        $user->notify(new AccountApprovedNotification());

        // 5. Account Rejected
        $this->line("  {$lang} Sending: Account Rejected");
        $user->notify(new AccountRejectedNotification('Contenido inapropiado en el perfil'));

        // 6. New User Registration (for admins)
        $this->line("  {$lang} Sending: New User Registration (Admin notification)");
        $user->notify(new NewUserRegistrationNotification($user));

        // 7. Karma Level Up (only if karma level exists)
        $level = KarmaLevel::first();
        if ($level) {
            $this->line("  {$lang} Sending: Karma Level Up");
            $user->notify(new KarmaLevelUp($level));
        }

        // 8. Achievement Unlocked (only if achievement exists)
        $achievement = Achievement::first();
        if ($achievement) {
            $this->line("  {$lang} Sending: Achievement Unlocked");
            $user->notify(new AchievementUnlocked($achievement));
        }

        // 9. Karma Event Starting (only if event exists)
        $event = KarmaEvent::first();
        if ($event) {
            $this->line("  {$lang} Sending: Karma Event Starting");
            $user->notify(new KarmaEventStarting($event));
        }

        // 10. Legal Report Resolution (resolved)
        $this->line("  {$lang} Sending: Legal Report Resolution (Resolved)");
        $testReportResolved = $this->createTestLegalReport($locale, 'resolved');
        Mail::to($user->email)->send(new LegalReportResolutionMail($testReportResolved));

        // 11. Legal Report Resolution (rejected)
        $this->line("  {$lang} Sending: Legal Report Resolution (Rejected)");
        $testReportRejected = $this->createTestLegalReport($locale, 'rejected');
        Mail::to($user->email)->send(new LegalReportResolutionMail($testReportRejected));

        // Restore original email
        $user->email = $originalEmail;

        $this->line("  {$lang} ✓ {$locale} emails sent");
    }

    private function createTestLegalReport(string $locale, string $status): LegalReport
    {
        $report = new LegalReport([
            'reference_number' => 'REP-' . date('Ymd') . '-TEST',
            'type' => 'copyright',
            'content_url' => 'https://example.com/test-content',
            'reporter_name' => 'Test Reporter',
            'reporter_email' => 'test@example.com',
            'description' => 'This is a test legal report for email testing purposes.',
            'status' => $status,
            'locale' => $locale,
            'user_response' => $status === 'resolved'
                ? 'We have reviewed your report and taken action. The content has been removed from our platform.'
                : 'We have reviewed your report but found no violation of our terms. The content remains on our platform.',
            'reviewed_at' => now(),
        ]);

        // Don't save to database, just return the model instance
        return $report;
    }
}
