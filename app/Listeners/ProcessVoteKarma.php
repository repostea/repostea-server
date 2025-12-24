<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Vote;
use App\Services\KarmaService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

final class ProcessVoteKarma implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private KarmaService $karmaService,
    ) {}

    public function handle($event): void
    {
        if (! isset($event->models) || empty($event->models) || ! ($event->models[0] instanceof Vote)) {
            return;
        }

        try {
            $vote = $event->models[0];

            $this->karmaService->processVoteKarma($vote);

        } catch (Exception $e) {
            Log::error('Error processing vote karma: ' . $e->getMessage());
        }
    }
}
