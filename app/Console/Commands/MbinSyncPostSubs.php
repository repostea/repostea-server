<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MbinImportTracking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class MbinSyncPostSubs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mbin:sync-post-subs
                            {--dry-run : Show what would be updated without making changes}
                            {--limit= : Limit number of posts to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync sub_id for posts imported from Mbin that have NULL sub_id';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info('📦 Syncing sub_id for posts with NULL sub_id...');

        $query = DB::table('posts')
            ->whereNull('sub_id')
            ->where('is_external_import', true)
            ->where('source_name', 'Mbin')
            ->orderBy('id');

        if ($limit) {
            $query->limit($limit);
        }

        $posts = $query->get();

        if ($posts->isEmpty()) {
            $this->info('✅ No posts with NULL sub_id found');

            return self::SUCCESS;
        }

        $this->info("Found {$posts->count()} posts with NULL sub_id");

        $bar = $this->output->createProgressBar($posts->count());
        $bar->start();

        $updated = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($posts as $post) {
            if (! preg_match('/mbin_entry_(\d+)/', $post->external_id ?? '', $matches)) {
                $skipped++;
                $bar->advance();

                continue;
            }

            $mbinEntryId = (int) $matches[1];

            $mbinEntry = DB::connection('mbin')
                ->table('entry')
                ->where('id', $mbinEntryId)
                ->first();

            if (! $mbinEntry || ! $mbinEntry->magazine_id) {
                $notFound++;
                $bar->advance();

                continue;
            }

            $reposteaSubId = MbinImportTracking::getReposteaId('magazine', $mbinEntry->magazine_id);

            if (! $reposteaSubId) {
                Log::warning("Magazine {$mbinEntry->magazine_id} not found in tracking for post {$post->id}");
                $notFound++;
                $bar->advance();

                continue;
            }

            if (! $dryRun) {
                DB::table('posts')
                    ->where('id', $post->id)
                    ->update(['sub_id' => $reposteaSubId]);
            }

            $this->line(" Post {$post->id} -> sub_id: {$reposteaSubId}", verbosity: 'v');
            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $action = $dryRun ? 'Would update' : 'Updated';
        $this->info("✅ {$action}: {$updated}, Skipped: {$skipped}, Not found in Mbin: {$notFound}");

        Log::info('MbinSyncPostSubs completed', [
            'updated' => $updated,
            'skipped' => $skipped,
            'not_found' => $notFound,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }
}
