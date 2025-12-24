<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ActivityPubFollower;
use App\Services\ActivityPubService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to send Delete activity for the actor to all followers.
 * This effectively removes the account from the Fediverse.
 */
final class ActivityPubDeleteActor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activitypub:delete-actor
        {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Delete the ActivityPub actor from the Fediverse (sends Delete activity to all followers)';

    public function __construct(
        private readonly ActivityPubService $activityPub,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->activityPub->isEnabled()) {
            $this->error('ActivityPub is not enabled.');

            return self::FAILURE;
        }

        $followerCount = ActivityPubFollower::count();

        if ($followerCount === 0) {
            $this->warn('No followers to notify.');

            return self::SUCCESS;
        }

        $actorId = $this->activityPub->getActorId();

        $this->warn("This will delete the actor: {$actorId}");
        $this->warn("This action will notify {$followerCount} followers that the account is deleted.");
        $this->warn('The followers will be removed from the database after sending.');

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to proceed?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $this->info("Sending Delete activity to {$followerCount} followers...");

        // Build the Delete Actor activity
        $activity = $this->activityPub->buildDeleteActorActivity();

        // Get unique inboxes
        $inboxes = ActivityPubFollower::getUniqueInboxes();
        $successCount = 0;
        $failCount = 0;

        foreach ($inboxes as $inbox) {
            $this->line("  Sending to: {$inbox}");

            $success = $this->activityPub->sendToInbox($inbox, $activity);

            if ($success) {
                $successCount++;
                $this->info('    ✓ Delivered');
            } else {
                $failCount++;
                $this->error('    ✗ Failed');
            }
        }

        $this->newLine();
        $this->info("Delivery complete: {$successCount} succeeded, {$failCount} failed");

        // Remove all followers from database
        if ($this->confirm('Remove all followers from the database?', true)) {
            $deleted = ActivityPubFollower::query()->delete();
            $this->info("Removed {$deleted} followers from database.");

            Log::info("ActivityPub: Actor deleted, removed {$deleted} followers");
        }

        $this->newLine();
        $this->warn('Remember to set ACTIVITYPUB_ENABLED=false in .env to prevent new follows.');

        return self::SUCCESS;
    }
}
