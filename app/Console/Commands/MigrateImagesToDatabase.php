<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Image;
use App\Models\Post;
use App\Models\User;
use App\Services\ImageService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

final class MigrateImagesToDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:migrate-to-db {--type=all : Type of images to migrate (avatars, thumbnails, all)} {--limit= : Limit number of images to migrate} {--dry-run : Show what would be migrated without actually migrating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing image URLs (from internet) to database storage system';

    protected ImageService $imageService;

    protected int $successCount = 0;

    protected int $errorCount = 0;

    protected int $skippedCount = 0;

    public function __construct(ImageService $imageService)
    {
        parent::__construct();
        $this->imageService = $imageService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $dryRun = $this->option('dry-run');

        $this->info('🚀 Starting image migration to database...');
        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be made');
        }
        $this->newLine();

        if (in_array($type, ['avatars', 'all'])) {
            $this->migrateAvatars($limit, $dryRun);
        }

        if (in_array($type, ['thumbnails', 'all'])) {
            $this->migrateThumbnails($limit, $dryRun);
        }

        $this->newLine();
        $this->info('📊 Migration Summary:');
        $this->info("  ✅ Success: {$this->successCount}");
        $this->info("  ❌ Errors: {$this->errorCount}");
        $this->info("  ⏭️  Skipped: {$this->skippedCount}");

        return 0;
    }

    protected function migrateAvatars(?int $limit, bool $dryRun): void
    {
        $this->info('👤 Migrating user avatars...');

        // Check both avatar and avatar_url fields
        $query = User::where(function ($q): void {
            $q->whereNotNull('avatar')->where('avatar', '!=', '')
                ->orWhere(function ($q2): void {
                    $q2->whereNotNull('avatar_url')->where('avatar_url', '!=', '');
                });
        })->whereNull('avatar_image_id');

        if ($limit) {
            $query->limit($limit);
        }

        $users = $query->get();
        $this->info("Found {$users->count()} users with avatar URLs");

        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->start();

        foreach ($users as $user) {
            try {
                if ($dryRun) {
                    $this->skippedCount++;
                    $progressBar->advance();

                    continue;
                }

                // Download image from URL (check avatar_url first, then avatar)
                $avatarUrl = $user->avatar_url ?: $user->avatar;
                $tempFile = $this->downloadImage($avatarUrl);
                if (! $tempFile) {
                    $this->errorCount++;
                    $progressBar->advance();

                    continue;
                }

                // Upload to database using ImageService
                $image = $this->imageService->uploadAvatar($tempFile, $user->id);

                // Update user with new image reference
                $user->avatar_image_id = $image->id;
                // Keep old URLs for backward compatibility
                $user->save();

                $this->successCount++;

                // Clean up temp file
                @unlink($tempFile->getRealPath());
            } catch (Exception $e) {
                $this->errorCount++;
                $this->newLine();
                $this->error("Failed to migrate avatar for user {$user->id}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    protected function migrateThumbnails(?int $limit, bool $dryRun): void
    {
        $this->info('🖼️  Migrating post thumbnails...');

        $query = Post::whereNotNull('thumbnail_url')
            ->where('thumbnail_url', '!=', '')
            ->whereNull('thumbnail_image_id');

        if ($limit) {
            $query->limit($limit);
        }

        $posts = $query->get();
        $this->info("Found {$posts->count()} posts with thumbnail URLs");

        $progressBar = $this->output->createProgressBar($posts->count());
        $progressBar->start();

        foreach ($posts as $post) {
            try {
                if ($dryRun) {
                    $this->skippedCount++;
                    $progressBar->advance();

                    continue;
                }

                // Link internal URLs to existing images
                if ($this->isInternalImageUrl($post->thumbnail_url)) {
                    $image = $this->findImageByUrl($post->thumbnail_url);
                    if ($image) {
                        $post->thumbnail_image_id = $image->id;
                        $post->save();
                        $this->successCount++;
                    } else {
                        $this->errorCount++;
                    }
                    $progressBar->advance();

                    continue;
                }

                // Download image from external URL
                $tempFile = $this->downloadImage($post->thumbnail_url);
                if (! $tempFile) {
                    $this->errorCount++;
                    $progressBar->advance();

                    continue;
                }

                // Upload to database using ImageService
                $image = $this->imageService->uploadThumbnail($tempFile, $post->id, $post->user_id);

                // Update post with new image reference
                $post->thumbnail_image_id = $image->id;
                // Keep old URL for backward compatibility
                $post->save();

                $this->successCount++;

                // Clean up temp file
                @unlink($tempFile->getRealPath());
            } catch (Exception $e) {
                $this->errorCount++;
                $this->newLine();
                $this->error("Failed to migrate thumbnail for post {$post->id}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    protected function downloadImage(string $url): ?UploadedFile
    {
        try {
            // Download image with timeout
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                return null;
            }

            // Get content type
            $contentType = $response->header('Content-Type');
            if (! str_starts_with($contentType, 'image/')) {
                return null;
            }

            // Determine extension from content type
            $extension = match ($contentType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => 'jpg',
            };

            // Create temporary file
            $tempPath = tempnam(sys_get_temp_dir(), 'img_');
            $tempPathWithExt = $tempPath . '.' . $extension;
            rename($tempPath, $tempPathWithExt);

            // Save image content
            file_put_contents($tempPathWithExt, $response->body());

            // Create UploadedFile instance
            return new UploadedFile(
                $tempPathWithExt,
                basename($tempPathWithExt),
                $contentType,
                null,
                true, // test mode - don't validate if file was uploaded via HTTP
            );
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Check if URL is an internal image URL (already in our system).
     */
    protected function isInternalImageUrl(string $url): bool
    {
        // Match /api/v1/images/HASH or /api/v1/images/HASH/size
        return (bool) preg_match('#^/api/v1/images/[a-f0-9]{64}(?:/\w+)?$#', $url);
    }

    /**
     * Find existing image by internal URL.
     */
    protected function findImageByUrl(string $url): ?Image
    {
        if (preg_match('#/api/v1/images/([a-f0-9]{64})#', $url, $matches)) {
            return Image::where('hash', $matches[1])->first();
        }

        return null;
    }
}
