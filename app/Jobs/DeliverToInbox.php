<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ActivityPubActor;
use App\Models\ActivityPubDelivery;
use App\Models\ActivityPubDeliveryLog;
use App\Services\ActivityPubService;
use App\Services\MultiActorActivityPubService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to deliver an activity to a specific inbox.
 *
 * Supports both legacy single-actor and new multi-actor delivery.
 */
final class DeliverToInbox implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * Calculate the number of seconds to wait before retrying.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 7200]; // 1min, 5min, 15min, 1hr, 2hr
    }

    /**
     * @param  string|int  $actorIdOrActivityId  Actor ID (int) for multi-actor, Activity ID (string) for legacy
     * @param  array<string, mixed>  $activity
     * @param  string  $mode  'legacy' or 'multi_actor'
     */
    public function __construct(
        public readonly string|int $actorIdOrActivityId,
        public readonly string $inboxUrl,
        public readonly array $activity,
        public readonly string $mode = 'legacy',
    ) {
        $this->onQueue(config('activitypub.queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(ActivityPubService $legacyService, MultiActorActivityPubService $multiActorService): void
    {
        // Determine the activity ID for tracking
        $activityId = $this->mode === 'multi_actor'
            ? ($this->activity['id'] ?? 'unknown-' . time())
            : $this->actorIdOrActivityId;

        $delivery = ActivityPubDelivery::where('activity_id', $activityId)
            ->where('target_inbox', $this->inboxUrl)
            ->first();

        if ($delivery === null) {
            // Create delivery record if missing
            $delivery = ActivityPubDelivery::create([
                'activity_id' => $activityId,
                'target_inbox' => $this->inboxUrl,
                'status' => ActivityPubDelivery::STATUS_PENDING,
            ]);
        }

        if ($delivery->status === ActivityPubDelivery::STATUS_DELIVERED) {
            return;
        }

        // Determine actor ID for logging
        $actorIdForLog = $this->mode === 'multi_actor' ? (int) $this->actorIdOrActivityId : null;
        $activityType = $this->activity['type'] ?? 'Unknown';

        try {
            $success = $this->sendActivity($legacyService, $multiActorService);

            if ($success) {
                $delivery->markDelivered();
                Log::info("ActivityPub: Delivered {$activityId} to {$this->inboxUrl}");

                // Log successful delivery for stats
                if ($actorIdForLog !== null) {
                    ActivityPubDeliveryLog::logSuccess($actorIdForLog, $this->inboxUrl, $activityType);
                }
            } else {
                $delivery->markFailed('HTTP request failed');

                // Log failed delivery for stats
                if ($actorIdForLog !== null) {
                    ActivityPubDeliveryLog::logFailure(
                        $actorIdForLog,
                        $this->inboxUrl,
                        $activityType,
                        null,
                        'HTTP request failed',
                        $this->attempts(),
                    );
                }

                if ($delivery->canRetry()) {
                    $this->release($this->backoff()[$this->attempts() - 1] ?? 7200);
                }
            }
        } catch (Exception $e) {
            $delivery->markFailed($e->getMessage());
            Log::error("ActivityPub: Delivery failed to {$this->inboxUrl}", [
                'error' => $e->getMessage(),
            ]);

            // Log failed delivery for stats
            if ($actorIdForLog !== null) {
                ActivityPubDeliveryLog::logFailure(
                    $actorIdForLog,
                    $this->inboxUrl,
                    $activityType,
                    null,
                    $e->getMessage(),
                    $this->attempts(),
                );
            }

            if ($delivery->canRetry()) {
                $this->release($this->backoff()[$this->attempts() - 1] ?? 7200);
            }
        }
    }

    /**
     * Send the activity using the appropriate service.
     */
    private function sendActivity(
        ActivityPubService $legacyService,
        MultiActorActivityPubService $multiActorService,
    ): bool {
        if ($this->mode === 'multi_actor') {
            // Multi-actor mode: use the specific actor to sign the request
            $actor = ActivityPubActor::find($this->actorIdOrActivityId);

            if ($actor === null) {
                Log::warning('ActivityPub: Actor not found for delivery', [
                    'actor_id' => $this->actorIdOrActivityId,
                ]);

                return false;
            }

            return $multiActorService->sendToInbox($actor, $this->inboxUrl, $this->activity);
        }

        // Legacy mode: use the instance actor
        return $legacyService->sendToInbox($this->inboxUrl, $this->activity);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        $activityId = $this->mode === 'multi_actor'
            ? ($this->activity['id'] ?? 'unknown')
            : $this->actorIdOrActivityId;

        Log::error("ActivityPub: Delivery permanently failed to {$this->inboxUrl}", [
            'activity_id' => $activityId,
            'error' => $exception->getMessage(),
        ]);

        ActivityPubDelivery::where('activity_id', $activityId)
            ->where('target_inbox', $this->inboxUrl)
            ->update(['status' => ActivityPubDelivery::STATUS_FAILED]);
    }
}
