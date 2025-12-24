<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invitation;
use Exception;
use Illuminate\Console\Command;

final class InvitationCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invitation:create
                            {--count=1 : Number of invitations to create}
                            {--max-uses=1 : Maximum number of times each invitation can be used}
                            {--expires-in= : Expiration time (e.g., "7 days", "1 month", "never")}
                            {--user= : User ID who creates the invitation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create invitation codes for user registration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = (int) $this->option('count');
        $maxUses = (int) $this->option('max-uses');
        $expiresIn = $this->option('expires-in');
        $userId = $this->option('user') ? (int) $this->option('user') : null;

        if ($count < 1) {
            $this->error('Count must be at least 1');

            return 1;
        }

        if ($maxUses < 1) {
            $this->error('Max uses must be at least 1');

            return 1;
        }

        // Calculate expiration date
        $expiresAt = null;
        if ($expiresIn && $expiresIn !== 'never') {
            try {
                $expiresAt = now()->modify("+{$expiresIn}");
            } catch (Exception $e) {
                $this->error("Invalid expiration format: {$expiresIn}");
                $this->info('Examples: "7 days", "1 month", "2 weeks", "never"');

                return 1;
            }
        }

        $this->info("Creating {$count} invitation(s)...");
        $this->newLine();

        $invitations = [];
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            $invitation = Invitation::create([
                'code' => Invitation::generateCode(),
                'created_by' => $userId,
                'max_uses' => $maxUses,
                'expires_at' => $expiresAt,
            ]);

            $invitations[] = $invitation;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Show summary
        $this->info('✅ Invitations created successfully!');
        $this->newLine();

        $headers = ['Code', 'Max Uses', 'Expires At', 'Registration URL'];
        $rows = [];

        foreach ($invitations as $invitation) {
            $registrationUrl = config('app.client_url') . '/auth/register?invitation=' . $invitation->code;
            $rows[] = [
                $invitation->code,
                $invitation->max_uses,
                $invitation->expires_at ? $invitation->expires_at->format('Y-m-d H:i:s') : 'Never',
                $registrationUrl,
            ];
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->info('💡 Share these URLs with users to allow them to register');

        return 0;
    }
}
