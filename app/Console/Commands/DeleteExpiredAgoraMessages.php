<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AgoraMessage;
use Illuminate\Console\Command;

final class DeleteExpiredAgoraMessages extends Command
{
    protected $signature = 'agora:delete-expired';

    protected $description = 'Delete expired Agora messages and their replies';

    public function handle(): int
    {
        $this->info('Checking for expired Agora messages...');

        // Find all expired top-level messages
        $expiredMessages = AgoraMessage::whereNull('parent_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $count = $expiredMessages->count();

        if ($count === 0) {
            $this->info('No expired messages found.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} expired thread(s).");

        foreach ($expiredMessages as $message) {
            // Force delete all replies (permanent)
            $repliesCount = $message->replies()->count();
            $message->replies()->forceDelete();

            // Force delete the main message (permanent)
            $message->forceDelete();

            $this->line("Permanently deleted thread #{$message->id} with {$repliesCount} replies.");
        }

        $this->info("Successfully deleted {$count} expired thread(s).");

        return self::SUCCESS;
    }
}
