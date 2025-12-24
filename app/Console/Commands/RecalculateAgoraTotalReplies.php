<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AgoraMessage;
use Illuminate\Console\Command;

final class RecalculateAgoraTotalReplies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agora:recalculate-total-replies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate total_replies_count for all agora messages';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Recalculating total_replies_count for all agora messages...');

        // Get all messages ordered by depth (leaf nodes first)
        // We process from bottom-up so nested counts are accurate
        $messages = AgoraMessage::withTrashed()
            ->orderByRaw('(SELECT COUNT(*) FROM agora_messages AS children WHERE children.parent_id = agora_messages.id) ASC')
            ->get();

        $bar = $this->output->createProgressBar($messages->count());
        $bar->start();

        foreach ($messages as $message) {
            $message->total_replies_count = $message->calculateTotalRepliesCount();
            $message->saveQuietly();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done! Recalculated ' . $messages->count() . ' messages.');

        return Command::SUCCESS;
    }
}
