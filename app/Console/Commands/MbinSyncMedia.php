<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class MbinSyncMedia extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mbin:sync-media';

    /**
     * The console command description.
     */
    protected $description = 'Sync all media (images, thumbnails, source URLs) for Mbin imported posts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🖼️  Syncing media for Mbin imported posts...');
        $this->newLine();

        Log::info('====== MBIN MEDIA SYNC STARTED ======', [
            'timestamp' => now()->toDateTimeString(),
        ]);

        $mbinBaseUrl = config('services.mbin.url');
        if (! $mbinBaseUrl) {
            $this->error('MBIN_URL not configured (services.mbin.url)');
            Log::error('MBIN_URL not configured');

            return 1;
        }

        // Step 1: Update image URLs
        $this->info('1️⃣  Updating image URLs...');
        $imageCount = $this->updateImageUrls($mbinBaseUrl);
        $this->info("   ✅ Updated {$imageCount} image URLs");
        $this->newLine();

        // Step 2: Update thumbnails
        $this->info('2️⃣  Updating thumbnails...');
        $thumbnailCount = $this->updateThumbnails($mbinBaseUrl);
        $this->info("   ✅ Updated {$thumbnailCount} thumbnails");
        $this->newLine();

        // Step 3: Update source URLs
        $this->info('3️⃣  Updating source URLs...');
        $sourceCount = $this->updateSourceUrls($mbinBaseUrl);
        $this->info("   ✅ Updated {$sourceCount} source URLs");
        $this->newLine();

        $total = $imageCount + $thumbnailCount + $sourceCount;
        $this->info("✨ Media sync completed successfully! Total updates: {$total}");

        Log::info('====== MBIN MEDIA SYNC COMPLETED ======', [
            'timestamp' => now()->toDateTimeString(),
            'image_urls' => $imageCount,
            'thumbnails' => $thumbnailCount,
            'source_urls' => $sourceCount,
            'total' => $total,
        ]);

        return 0;
    }

    /**
     * Update image URLs for posts imported from Mbin that have image_id but missing URL.
     */
    private function updateImageUrls(string $mbinBaseUrl): int
    {
        $posts = DB::table('posts')
            ->where('source_name', 'Mbin')
            ->where(function ($query): void {
                $query->whereNull('url')
                    ->orWhere('url', '');
            })
            ->get();

        $bar = $this->output->createProgressBar($posts->count());
        $bar->start();

        $updated = 0;

        foreach ($posts as $post) {
            preg_match('/mbin_entry_(\d+)/', $post->external_id, $matches);
            if (! isset($matches[1])) {
                $bar->advance();

                continue;
            }

            $mbinEntryId = (int) $matches[1];

            $mbinEntry = DB::connection('mbin')->table('entry')
                ->where('id', $mbinEntryId)
                ->first();

            if (! $mbinEntry || ! $mbinEntry->image_id) {
                $bar->advance();

                continue;
            }

            $image = DB::connection('mbin')->table('image')
                ->where('id', $mbinEntry->image_id)
                ->first();

            if (! $image || ! $image->file_path) {
                $bar->advance();

                continue;
            }

            $imageUrl = rtrim($mbinBaseUrl, '/') . '/media/' . $image->file_path;

            DB::table('posts')
                ->where('id', $post->id)
                ->update([
                    'url' => $imageUrl,
                    'updated_at' => now(),
                ]);

            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $updated;
    }

    /**
     * Update thumbnail URLs for link/article/video posts imported from Mbin.
     */
    private function updateThumbnails(string $mbinBaseUrl): int
    {
        $posts = DB::table('posts')
            ->where('source_name', 'Mbin')
            ->whereIn('content_type', ['link', 'text', 'video', 'audio'])
            ->whereNull('thumbnail_url')
            ->get();

        $bar = $this->output->createProgressBar($posts->count());
        $bar->start();

        $updated = 0;

        foreach ($posts as $post) {
            preg_match('/mbin_entry_(\d+)/', $post->external_id, $matches);
            if (! isset($matches[1])) {
                $bar->advance();

                continue;
            }

            $mbinEntryId = (int) $matches[1];

            $mbinEntry = DB::connection('mbin')->table('entry')
                ->where('id', $mbinEntryId)
                ->first();

            if (! $mbinEntry || ! $mbinEntry->image_id) {
                $bar->advance();

                continue;
            }

            $image = DB::connection('mbin')->table('image')
                ->where('id', $mbinEntry->image_id)
                ->first();

            if (! $image || ! $image->file_path) {
                $bar->advance();

                continue;
            }

            $thumbnailUrl = rtrim($mbinBaseUrl, '/') . '/media/' . $image->file_path;

            DB::table('posts')
                ->where('id', $post->id)
                ->update([
                    'thumbnail_url' => $thumbnailUrl,
                    'updated_at' => now(),
                ]);

            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $updated;
    }

    /**
     * Update source_url for existing Mbin posts.
     */
    private function updateSourceUrls(string $mbinBaseUrl): int
    {
        $posts = DB::table('posts')
            ->where('is_external_import', 1)
            ->where('source_name', 'Mbin')
            ->whereNull('source_url')
            ->get();

        $bar = $this->output->createProgressBar($posts->count());
        $bar->start();

        $updated = 0;

        foreach ($posts as $post) {
            // Get Mbin ID from tracking
            $tracking = DB::table('mbin_import_tracking')
                ->where('entity_type', 'entry')
                ->where('repostea_id', $post->id)
                ->first();

            if (! $tracking) {
                $bar->advance();

                continue;
            }

            $mbinEntryId = $tracking->mbin_id;

            // Get entry from Mbin
            $mbinEntry = DB::connection('mbin')->table('entry')
                ->where('id', $mbinEntryId)
                ->first();

            if (! $mbinEntry || ! $mbinEntry->magazine_id) {
                $bar->advance();

                continue;
            }

            // Get magazine
            $magazine = DB::connection('mbin')->table('magazine')
                ->where('id', $mbinEntry->magazine_id)
                ->first();

            if (! $magazine) {
                $bar->advance();

                continue;
            }

            // Build URL
            $sourceUrl = rtrim($mbinBaseUrl, '/') . '/m/' . $magazine->name . '/t/' . $mbinEntry->id . '/' . $mbinEntry->slug;

            // Update post
            DB::table('posts')
                ->where('id', $post->id)
                ->update(['source_url' => $sourceUrl]);

            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $updated;
    }
}
