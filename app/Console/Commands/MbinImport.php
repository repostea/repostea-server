<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MbinImportTracking;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class MbinImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mbin:import
                            {--users : Import only users}
                            {--magazines : Import only magazines/subs}
                            {--posts : Import only posts/entries}
                            {--comments : Import only comments}
                            {--votes : Import only votes}
                            {--subscriptions : Import only magazine subscriptions}
                            {--moderators : Import only magazine moderators and owners}
                            {--sync-status : Synchronize deletion/visibility status}
                            {--all : Import everything}
                            {--sync : Synchronization mode (only new records)}
                            {--force : Force reimport of existing records}
                            {--limit= : Limit number of records to import}
                            {--hours= : Only import records from last N hours}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Import data from Mbin (PostgreSQL) to Repostea (MySQL)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            Log::info('====== MBIN IMPORT STARTED ======', [
                'timestamp' => now()->toDateTimeString(),
                'command' => 'mbin:import',
            ]);

            $this->info('🚀 Starting import from Mbin...');
            $this->newLine();

            // Verify Mbin connection
            if (! $this->testMbinConnection()) {
                $this->error('❌ Could not connect to Mbin database');
                Log::error('Mbin import failed: Could not connect to Mbin database');

                return 1;
            }

            $this->info('✅ Mbin connection established successfully');
            Log::info('Mbin connection established successfully');
            $this->newLine();

            // Determine what to import
            $importAll = $this->option('all');
            $importUsers = $this->option('users') || $importAll;
            $importMagazines = $this->option('magazines') || $importAll;
            $importPosts = $this->option('posts') || $importAll;
            $importComments = $this->option('comments') || $importAll;
            $importVotes = $this->option('votes') || $importAll;
            $importSubscriptions = $this->option('subscriptions') || $importAll;
            $importModerators = $this->option('moderators') || $importAll;
            $syncStatus = $this->option('sync-status');

            $syncMode = $this->option('sync');
            $force = $this->option('force');
            $limit = $this->option('limit') ? (int) $this->option('limit') : null;
            $hours = $this->option('hours') ? (int) $this->option('hours') : null;

            if (! $importUsers && ! $importMagazines && ! $importPosts && ! $importComments && ! $importVotes && ! $importSubscriptions && ! $importModerators && ! $syncStatus) {
                $this->error('You must specify at least one option: --users, --magazines, --posts, --comments, --votes, --subscriptions, --moderators, --sync-status or --all');

                return 1;
            }

            // Show summary
            $this->showSummary($importUsers, $importMagazines, $importPosts, $importComments, $importVotes, $importSubscriptions, $importModerators, $syncStatus, $syncMode, $force, $limit, $hours);

            if (! $this->confirm('Do you want to continue with the import?', true)) {
                $this->info('Import cancelled.');

                return 0;
            }

            $this->newLine();

            if ($importUsers) {
                $this->importUsers($syncMode, $force, $limit, $hours);
            }

            if ($importMagazines) {
                $this->importMagazines($syncMode, $force, $limit, $hours);
            }

            if ($importPosts) {
                $this->importPosts($syncMode, $force, $limit, $hours);
            }

            if ($importComments) {
                $this->importComments($syncMode, $force, $limit, $hours);
            }

            if ($importVotes) {
                $this->importVotes($syncMode, $force, $limit, $hours);
            }

            if ($importSubscriptions) {
                $this->importSubscriptions($syncMode, $force, $limit, $hours);
            }

            if ($importModerators) {
                $this->importModerators($syncMode, $force, $limit, $hours);
            }

            if ($syncStatus) {
                $this->syncPostsStatus();
            }

            $this->newLine();
            $this->info('✨ Import completed successfully!');

            Log::info('====== MBIN IMPORT COMPLETED SUCCESSFULLY ======', [
                'timestamp' => now()->toDateTimeString(),
            ]);

            return 0;

        } catch (Exception $e) {
            $this->error("❌ Error during import: {$e->getMessage()}");
            Log::error('====== MBIN IMPORT FAILED ======', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toDateTimeString(),
            ]);

            return 1;
        }
    }

    /**
     * Test connection to Mbin database.
     */
    private function testMbinConnection(): bool
    {
        try {
            DB::connection('mbin')->getPdo();

            return true;
        } catch (Exception $e) {
            Log::error("Error connecting to Mbin: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Show import summary.
     */
    private function showSummary(bool $users, bool $magazines, bool $posts, bool $comments, bool $votes, bool $subscriptions, bool $moderators, bool $syncStatus, bool $sync, bool $force, ?int $limit, ?int $hours): void
    {
        $this->info('📋 Import summary:');
        $this->newLine();

        $items = [];
        if ($users) {
            $items[] = '👥 Users';
        }
        if ($magazines) {
            $items[] = '📁 Magazines/Subs';
        }
        if ($posts) {
            $items[] = '📝 Posts/Entries';
        }
        if ($comments) {
            $items[] = '💬 Comments';
        }
        if ($votes) {
            $items[] = '👍 Votes';
        }
        if ($subscriptions) {
            $items[] = '🔖 Magazine Subscriptions';
        }
        if ($moderators) {
            $items[] = '👮 Magazine Moderators & Owners';
        }
        if ($syncStatus) {
            $items[] = '🔄 Sync deletion/visibility status';
        }

        foreach ($items as $item) {
            $this->line("  • {$item}");
        }

        $this->newLine();
        $this->line('  Mode: ' . ($sync ? '🔄 Synchronization (only new)' : '📦 Full import'));
        if ($force) {
            $this->line('  ⚠️  Force reimport existing');
        }
        if ($hours) {
            $this->line("  ⏰ Time filter: Last {$hours} hours");
        }
        if ($limit) {
            $this->line("  📊 Limit: {$limit} records per entity");
        }
        $this->newLine();
    }

    /**
     * Import users.
     */
    private function importUsers(bool $syncMode, bool $force, ?int $limit, ?int $hours): void
    {
        $this->info('👥 Importing users...');
        Log::info('Starting users import', ['sync' => $syncMode, 'force' => $force, 'limit' => $limit, 'hours' => $hours]);

        try {
            $query = DB::connection('mbin')->table('user')
                ->orderBy('id');

            if ($hours) {
                $sinceDate = Carbon::now()->subHours($hours);
                $query->where('created_at', '>=', $sinceDate);
            }

            if ($limit) {
                $query->limit($limit);
            }

            $mbinUsers = $query->get();
            $bar = $this->output->createProgressBar($mbinUsers->count());
            $bar->start();

            $imported = 0;
            $skipped = 0;

            foreach ($mbinUsers as $mbinUser) {
                $wasImported = MbinImportTracking::wasImported('user', $mbinUser->id);

                if (! $force && $wasImported) {
                    // User already imported, but we can sync verification if needed
                    $reposteaUserId = MbinImportTracking::getReposteaId('user', $mbinUser->id);

                    if ($reposteaUserId && $mbinUser->is_verified) {
                        $existingUser = DB::table('users')->where('id', $reposteaUserId)->first();

                        if ($existingUser && ! $existingUser->email_verified_at) {
                            DB::table('users')
                                ->where('id', $reposteaUserId)
                                ->update(['email_verified_at' => now()->format('Y-m-d H:i:s')]);
                            Log::info("User {$mbinUser->username} (ID {$reposteaUserId}) email verification synced from Mbin");
                        }
                    }

                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $reposteaUserId = $this->createUser($mbinUser);

                if ($reposteaUserId) {
                    MbinImportTracking::track('user', $mbinUser->id, $reposteaUserId);
                    $imported++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✅ Users imported: {$imported}, skipped: {$skipped}");
            Log::info('Users import completed', ['imported' => $imported, 'skipped' => $skipped]);
            $this->newLine();

        } catch (Exception $e) {
            $this->error("Error importing users: {$e->getMessage()}");
            Log::error("Error in importUsers: {$e->getMessage()}");
        }
    }

    /**
     * Import magazines as subs.
     */
    private function importMagazines(bool $syncMode, bool $force, ?int $limit, ?int $hours): void
    {
        $this->info('📁 Importing magazines as subs...');
        Log::info('Starting magazines import', ['sync' => $syncMode, 'force' => $force, 'limit' => $limit, 'hours' => $hours]);

        try {
            $query = DB::connection('mbin')->table('magazine')
                ->orderBy('id');

            if ($hours) {
                $sinceDate = Carbon::now()->subHours($hours);
                $query->where('created_at', '>=', $sinceDate);
            }

            if ($limit) {
                $query->limit($limit);
            }

            $mbinMagazines = $query->get();
            $bar = $this->output->createProgressBar($mbinMagazines->count());
            $bar->start();

            $imported = 0;
            $skipped = 0;

            foreach ($mbinMagazines as $mbinMagazine) {
                if (! $force && MbinImportTracking::wasImported('magazine', $mbinMagazine->id)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $reposteaSubId = $this->createSub($mbinMagazine);

                if ($reposteaSubId) {
                    // Track as magazine -> sub mapping
                    MbinImportTracking::track('magazine', $mbinMagazine->id, $reposteaSubId);

                    // Also create as tag
                    $this->createTag($mbinMagazine);

                    $imported++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✅ Magazines imported: {$imported}, skipped: {$skipped}");
            Log::info('Magazines import completed', ['imported' => $imported, 'skipped' => $skipped]);
            $this->newLine();

        } catch (Exception $e) {
            $this->error("Error importing magazines: {$e->getMessage()}");
            Log::error("Error in importMagazines: {$e->getMessage()}");
        }
    }

    /**
     * Import entries as posts.
     */
    private function importPosts(bool $syncMode, bool $force, ?int $limit, ?int $hours): void
    {
        $this->info('📝 Importing posts/entries...');
        Log::info('Starting posts import', ['sync' => $syncMode, 'force' => $force, 'limit' => $limit, 'hours' => $hours]);

        try {
            $query = DB::connection('mbin')->table('entry')
                ->orderBy('id');

            if ($hours) {
                $sinceDate = Carbon::now()->subHours($hours);
                $query->where('created_at', '>=', $sinceDate);
            }

            if ($limit) {
                $query->limit($limit);
            }

            $mbinEntries = $query->get();
            $bar = $this->output->createProgressBar($mbinEntries->count());
            $bar->start();

            $imported = 0;
            $skipped = 0;

            foreach ($mbinEntries as $mbinEntry) {
                if (! $force && MbinImportTracking::wasImported('entry', $mbinEntry->id)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $reposteaPostId = $this->createPost($mbinEntry);

                if ($reposteaPostId) {
                    MbinImportTracking::track('entry', $mbinEntry->id, $reposteaPostId);
                    $imported++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✅ Posts imported: {$imported}, skipped: {$skipped}");
            Log::info('Posts import completed', ['imported' => $imported, 'skipped' => $skipped]);
            $this->newLine();

        } catch (Exception $e) {
            $this->error("Error importing posts: {$e->getMessage()}");
            Log::error("Error in importPosts: {$e->getMessage()}");
        }
    }

    /**
     * Import comments.
     */
    private function importComments(bool $syncMode, bool $force, ?int $limit, ?int $hours): void
    {
        $this->info('💬 Importing comments...');
        Log::info('Starting comments import', ['sync' => $syncMode, 'force' => $force, 'limit' => $limit, 'hours' => $hours]);

        try {
            $query = DB::connection('mbin')->table('entry_comment')
                ->orderBy('id');

            if ($hours) {
                $sinceDate = Carbon::now()->subHours($hours);
                $query->where('created_at', '>=', $sinceDate);
            }

            if ($limit) {
                $query->limit($limit);
            }

            $mbinComments = $query->get();
            $bar = $this->output->createProgressBar($mbinComments->count());
            $bar->start();

            $imported = 0;
            $skipped = 0;

            foreach ($mbinComments as $mbinComment) {
                if (! $force && MbinImportTracking::wasImported('entry_comment', $mbinComment->id)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $reposteaCommentId = $this->createComment($mbinComment);

                if ($reposteaCommentId) {
                    MbinImportTracking::track('entry_comment', $mbinComment->id, $reposteaCommentId);
                    $imported++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✅ Comments imported: {$imported}, skipped: {$skipped}");
            Log::info('Comments import completed', ['imported' => $imported, 'skipped' => $skipped]);
            $this->newLine();

        } catch (Exception $e) {
            $this->error("Error importing comments: {$e->getMessage()}");
            Log::error("Error in importComments: {$e->getMessage()}");
        }
    }

    /**
     * Import votes (using favourite instead of entry_vote).
     */
    private function importVotes(bool $syncMode, bool $force, ?int $limit, ?int $hours): void
    {
        $this->info('👍 Importing post votes (from favourites)...');
        Log::info('Starting votes import (posts)', ['sync' => $syncMode, 'force' => $force, 'limit' => $limit, 'hours' => $hours]);

        try {
            $query = DB::connection('mbin')->table('favourite')
                ->whereNotNull('entry_id')
                ->orderBy('id');

            if ($hours) {
                $sinceDate = Carbon::now()->subHours($hours);
                $query->where('created_at', '>=', $sinceDate);
            }

            if ($limit) {
                $query->limit($limit);
            }

            $mbinFavourites = $query->get();
            $bar = $this->output->createProgressBar($mbinFavourites->count());
            $bar->start();

            $imported = 0;
            $skipped = 0;

            foreach ($mbinFavourites as $mbinFavourite) {
                if (! $force && MbinImportTracking::wasImported('favourite_entry', $mbinFavourite->id)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $reposteaVoteId = $this->createVoteFromFavourite($mbinFavourite);

                if ($reposteaVoteId) {
                    MbinImportTracking::track('favourite_entry', $mbinFavourite->id, $reposteaVoteId);
                    $imported++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✅ Post votes imported: {$imported}, skipped: {$skipped}");
            Log::info('Post votes import completed', ['imported' => $imported, 'skipped' => $skipped]);
            $this->newLine();

        } catch (Exception $e) {
            $this->error("Error importing votes: {$e->getMessage()}");
            Log::error("Error in importVotes: {$e->getMessage()}");
        }

        $this->info('👍 Importing comment votes (from favourites)...');
        Log::info('Starting votes import (comments)', ['sync' => $syncMode, 'force' => $force, 'limit' => $limit, 'hours' => $hours]);

        try {
            $query = DB::connection('mbin')->table('favourite')
                ->whereNotNull('entry_comment_id')
                ->orderBy('id');

            if ($hours) {
                $sinceDate = Carbon::now()->subHours($hours);
                $query->where('created_at', '>=', $sinceDate);
            }

            if ($limit) {
                $query->limit($limit);
            }

            $mbinFavourites = $query->get();
            $bar = $this->output->createProgressBar($mbinFavourites->count());
            $bar->start();

            $imported = 0;
            $skipped = 0;

            foreach ($mbinFavourites as $mbinFavourite) {
                if (! $force && MbinImportTracking::wasImported('favourite_comment', $mbinFavourite->id)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $reposteaVoteId = $this->createCommentVoteFromFavourite($mbinFavourite);

                if ($reposteaVoteId) {
                    MbinImportTracking::track('favourite_comment', $mbinFavourite->id, $reposteaVoteId);
                    $imported++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✅ Comment votes imported: {$imported}, skipped: {$skipped}");
            Log::info('Comment votes import completed', ['imported' => $imported, 'skipped' => $skipped]);
            $this->newLine();

        } catch (Exception $e) {
            $this->error("Error importing comment votes: {$e->getMessage()}");
            Log::error("Error in importCommentVotes: {$e->getMessage()}");
        }
    }

    /**
     * Create Repostea user from Mbin data.
     */
    private function createUser(object $mbinUser): ?int
    {
        try {
            $existingUser = DB::table('users')
                ->where('username', $mbinUser->username)
                ->orWhere('email', $mbinUser->email)
                ->first();

            if ($existingUser) {
                Log::warning("User {$mbinUser->username} already exists in Repostea with ID {$existingUser->id}");

                // If the user is verified in Mbin but not in Repostea, sync the activation date
                if ($mbinUser->is_verified && ! $existingUser->email_verified_at) {
                    DB::table('users')
                        ->where('id', $existingUser->id)
                        ->update([
                            'email_verified_at' => now()->format('Y-m-d H:i:s'),
                        ]);
                    Log::info("User {$mbinUser->username} (ID {$existingUser->id}) email verification synced from Mbin");
                }

                return $existingUser->id;
            }

            $avatarUrl = null;
            if ($mbinUser->avatar_id) {
                $avatar = DB::connection('mbin')
                    ->table('image')
                    ->where('id', $mbinUser->avatar_id)
                    ->first();

                if ($avatar && $avatar->file_path) {
                    $avatarUrl = config('database.connections.mbin.url', 'https://example.com') . '/media/' . $avatar->file_path;
                    Log::info("Avatar found for user {$mbinUser->username}: {$avatarUrl}");
                }
            }

            // Map Mbin fields to Repostea
            $userData = [
                'username' => $mbinUser->username,
                'email' => $mbinUser->email,
                'password' => $mbinUser->password, // Keep Mbin password hash
                'bio' => $mbinUser->about,
                'created_at' => Carbon::parse($mbinUser->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::parse($mbinUser->last_active ?? $mbinUser->created_at)->format('Y-m-d H:i:s'),
                'karma_points' => 0, // Start with 0, will be recalculated later
                'locale' => 'es',
                'is_guest' => 0,
                'email_verified_at' => $mbinUser->is_verified ? now()->format('Y-m-d H:i:s') : null,
                'avatar_url' => $avatarUrl,
            ];

            $userId = DB::table('users')->insertGetId($userData);

            Log::info("User {$mbinUser->username} imported successfully with ID {$userId}");

            return $userId;

        } catch (Exception $e) {
            Log::error("Error creating user {$mbinUser->username} (Mbin ID: {$mbinUser->id}): {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Create Repostea sub from Mbin magazine.
     */
    private function createSub(object $mbinMagazine): ?int
    {
        try {
            $createdBy = $this->getMagazineOwner($mbinMagazine->id);

            $existingSub = DB::table('subs')
                ->where('name', $mbinMagazine->name)
                ->first();

            if ($existingSub) {
                // If exists and has no created_by, update it
                if (! $existingSub->created_by && $createdBy) {
                    DB::table('subs')
                        ->where('id', $existingSub->id)
                        ->update(['created_by' => $createdBy]);
                    Log::info("Sub {$mbinMagazine->name} updated created_by to {$createdBy}");
                }

                return $existingSub->id;
            }

            $subData = [
                'name' => $mbinMagazine->name,
                'display_name' => $mbinMagazine->title ?? $mbinMagazine->name,
                'description' => $mbinMagazine->description,
                'rules' => $mbinMagazine->rules,
                'icon' => '📁', // Default icon
                'color' => '#3B82F6',
                'members_count' => $mbinMagazine->subscriptions_count ?? 0,
                'posts_count' => ($mbinMagazine->entry_count ?? 0) + ($mbinMagazine->post_count ?? 0),
                'is_private' => false,
                'is_adult' => $mbinMagazine->is_adult ?? false,
                'visibility' => $mbinMagazine->visibility ?? 'visible',
                'created_by' => $createdBy,
                'created_at' => Carbon::parse($mbinMagazine->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::parse($mbinMagazine->last_active ?? $mbinMagazine->created_at)->format('Y-m-d H:i:s'),
            ];

            $subId = DB::table('subs')->insertGetId($subData);

            Log::info("Sub {$mbinMagazine->name} imported successfully with ID {$subId}" . ($createdBy ? " (owner: {$createdBy})" : ''));

            return $subId;

        } catch (Exception $e) {
            Log::error("Error creating sub {$mbinMagazine->name} (Mbin ID: {$mbinMagazine->id}): {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Get magazine owner from Mbin and translate to Repostea user_id.
     */
    private function getMagazineOwner(int $mbinMagazineId): ?int
    {
        $owner = DB::connection('mbin')->table('moderator')
            ->where('magazine_id', $mbinMagazineId)
            ->where('is_owner', true)
            ->first(['user_id']);

        if (! $owner) {
            return null;
        }

        return MbinImportTracking::getReposteaId('user', $owner->user_id);
    }

    /**
     * Create Repostea post from Mbin entry.
     */
    private function createPost(object $mbinEntry): ?int
    {
        try {
            $existingPost = DB::table('posts')
                ->where('slug', $mbinEntry->slug)
                ->first();

            if ($existingPost) {
                Log::warning("Post with slug '{$mbinEntry->slug}' already exists with ID {$existingPost->id}");
                // Track even if already exists
                MbinImportTracking::track('entry', $mbinEntry->id, $existingPost->id);

                return $existingPost->id;
            }

            $reposteaUserId = MbinImportTracking::getReposteaId('user', $mbinEntry->user_id);
            if (! $reposteaUserId) {
                Log::warning("User Mbin ID {$mbinEntry->user_id} not found, skipping entry {$mbinEntry->id}");

                return null;
            }

            // Map content type (only valid enum values)
            $contentType = match ($mbinEntry->type) {
                'link' => 'link',
                'article' => 'text', // 'article' is not valid, use 'text'
                'image' => 'link',
                'video' => 'video',
                'audio' => 'audio',
                'poll' => 'poll',
                default => 'link',
            };

            $magazineName = null;
            if ($mbinEntry->magazine_id) {
                $magazine = DB::connection('mbin')->table('magazine')
                    ->where('id', $mbinEntry->magazine_id)
                    ->first();
                $magazineName = $magazine->name ?? null;
            }

            $sourceUrl = null;
            $mbinBaseUrl = config('services.mbin.url');
            if ($mbinBaseUrl && $magazineName && $mbinEntry->slug) {
                $sourceUrl = rtrim($mbinBaseUrl, '/') . '/m/' . $magazineName . '/t/' . $mbinEntry->id . '/' . $mbinEntry->slug;
            }

            $imageUrl = $mbinEntry->url;
            $thumbnailUrl = null;

            if ($mbinEntry->image_id && $mbinBaseUrl) {
                $image = DB::connection('mbin')->table('image')
                    ->where('id', $mbinEntry->image_id)
                    ->first();

                if ($image && $image->file_path) {
                    $constructedImageUrl = rtrim($mbinBaseUrl, '/') . '/media/' . $image->file_path;

                    // If type is 'image', the image IS the main content
                    if ($mbinEntry->type === 'image') {
                        $imageUrl = $constructedImageUrl;
                    } else {
                        // For other types (link, article, video), the image is a thumbnail
                        $thumbnailUrl = $constructedImageUrl;
                    }
                }
            }

            $subId = null;
            if ($mbinEntry->magazine_id) {
                $subId = MbinImportTracking::getReposteaId('magazine', $mbinEntry->magazine_id);
            }

            $postData = [
                'title' => $mbinEntry->title,
                'content' => $mbinEntry->body,
                'url' => $imageUrl,
                'thumbnail_url' => $thumbnailUrl,
                'user_id' => $reposteaUserId,
                'sub_id' => $subId,
                'type' => $imageUrl ? 'link' : 'article',
                'content_type' => $contentType,
                'is_original' => $mbinEntry->is_oc ?? false,
                'status' => 'published',
                'votes_count' => $mbinEntry->score ?? 0,
                'comment_count' => $mbinEntry->comment_count ?? 0,
                'views' => 0,
                'language_code' => $mbinEntry->lang ?? 'es',
                'is_external_import' => true,
                'external_id' => "mbin_entry_{$mbinEntry->id}",
                'source_name' => 'Mbin',
                'source_url' => $sourceUrl,
                'slug' => $mbinEntry->slug,
                'uuid' => \Illuminate\Support\Str::uuid(),
                'created_at' => Carbon::parse($mbinEntry->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::parse($mbinEntry->last_active ?? $mbinEntry->created_at)->format('Y-m-d H:i:s'),
            ];

            $postId = DB::table('posts')->insertGetId($postData);

            Log::info("Post '{$mbinEntry->title}' imported successfully with ID {$postId}");

            return $postId;

        } catch (Exception $e) {
            Log::error("Error creating post (Mbin Entry ID: {$mbinEntry->id}): {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Create Repostea comment from Mbin entry_comment.
     */
    private function createComment(object $mbinComment): ?int
    {
        try {
            $reposteaUserId = MbinImportTracking::getReposteaId('user', $mbinComment->user_id);
            if (! $reposteaUserId) {
                Log::warning("User Mbin ID {$mbinComment->user_id} not found, skipping comment {$mbinComment->id}");

                return null;
            }

            $reposteaPostId = MbinImportTracking::getReposteaId('entry', $mbinComment->entry_id);
            if (! $reposteaPostId) {
                Log::warning("Post Mbin ID {$mbinComment->entry_id} not found, skipping comment {$mbinComment->id}");

                return null;
            }

            $reposteaParentId = null;
            if ($mbinComment->parent_id) {
                $reposteaParentId = MbinImportTracking::getReposteaId('entry_comment', $mbinComment->parent_id);
            }

            $content = $mbinComment->body ?? '';

            // If comment has image, add it to content
            if ($mbinComment->image_id) {
                $image = DB::connection('mbin')->table('image')
                    ->where('id', $mbinComment->image_id)
                    ->first();

                if ($image && $image->file_path) {
                    $imageUrl = config('database.connections.mbin.url', 'https://example.com') . '/media/' . $image->file_path;
                    $imageMarkdown = "![imagen]({$imageUrl})";

                    // If there's text, add image after. If not, just the image
                    $content = trim($content) ? $content . "\n\n" . $imageMarkdown : $imageMarkdown;
                }
            }

            $commentData = [
                'content' => $content,
                'user_id' => $reposteaUserId,
                'post_id' => $reposteaPostId,
                'parent_id' => $reposteaParentId,
                'votes_count' => ($mbinComment->up_votes ?? 0) - ($mbinComment->down_votes ?? 0),
                'created_at' => Carbon::parse($mbinComment->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::parse($mbinComment->edited_at ?? $mbinComment->created_at)->format('Y-m-d H:i:s'),
            ];

            $commentId = DB::table('comments')->insertGetId($commentData);

            Log::info("Comment imported successfully with ID {$commentId}");

            return $commentId;

        } catch (Exception $e) {
            Log::error("Error creating comment (Mbin ID: {$mbinComment->id}): {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Create Repostea vote from Mbin favourite.
     */
    private function createVoteFromFavourite(object $mbinFavourite): ?int
    {
        try {
            $reposteaUserId = MbinImportTracking::getReposteaId('user', $mbinFavourite->user_id);
            if (! $reposteaUserId) {
                return null;
            }

            $reposteaPostId = MbinImportTracking::getReposteaId('entry', $mbinFavourite->entry_id);
            if (! $reposteaPostId) {
                return null;
            }

            $existingVote = DB::table('votes')
                ->where('user_id', $reposteaUserId)
                ->where('votable_type', 'App\Models\Post')
                ->where('votable_id', $reposteaPostId)
                ->first();

            if ($existingVote) {
                return $existingVote->id;
            }

            $voteData = [
                'user_id' => $reposteaUserId,
                'votable_type' => 'App\Models\Post',
                'votable_id' => $reposteaPostId,
                'value' => 1, // Favourites are always positive
                'type' => 'interesting', // Default type
                'created_at' => Carbon::parse($mbinFavourite->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::parse($mbinFavourite->created_at)->format('Y-m-d H:i:s'),
            ];

            $voteId = DB::table('votes')->insertGetId($voteData);

            return $voteId;

        } catch (Exception $e) {
            Log::error("Error creating vote from favourite (Mbin ID: {$mbinFavourite->id}): {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Create Repostea tag from Mbin magazine.
     */
    private function createTag(object $mbinMagazine): ?int
    {
        try {
            $existingTag = DB::table('tags')
                ->where('slug', $mbinMagazine->name)
                ->first();

            if ($existingTag) {
                Log::warning("Tag {$mbinMagazine->name} already exists with ID {$existingTag->id}");

                return $existingTag->id;
            }

            $tagData = [
                'name_key' => $mbinMagazine->name,
                'slug' => $mbinMagazine->name,
                'description_key' => $mbinMagazine->description,
                'created_at' => Carbon::parse($mbinMagazine->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::parse($mbinMagazine->last_active ?? $mbinMagazine->created_at)->format('Y-m-d H:i:s'),
            ];

            $tagId = DB::table('tags')->insertGetId($tagData);

            Log::info("Tag {$mbinMagazine->name} imported successfully with ID {$tagId}");

            return $tagId;

        } catch (Exception $e) {
            Log::error("Error creating tag {$mbinMagazine->name} (Mbin ID: {$mbinMagazine->id}): {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Create Repostea comment vote from Mbin favourite.
     */
    private function createCommentVoteFromFavourite(object $mbinFavourite): ?int
    {
        try {
            $reposteaUserId = MbinImportTracking::getReposteaId('user', $mbinFavourite->user_id);
            if (! $reposteaUserId) {
                return null;
            }

            $reposteaCommentId = MbinImportTracking::getReposteaId('entry_comment', $mbinFavourite->entry_comment_id);
            if (! $reposteaCommentId) {
                Log::warning("Comment Mbin ID {$mbinFavourite->entry_comment_id} not found, skipping favourite {$mbinFavourite->id}");

                return null;
            }

            $existingVote = DB::table('votes')
                ->where('user_id', $reposteaUserId)
                ->where('votable_type', 'App\Models\Comment')
                ->where('votable_id', $reposteaCommentId)
                ->first();

            if ($existingVote) {
                return $existingVote->id;
            }

            $voteData = [
                'user_id' => $reposteaUserId,
                'votable_type' => 'App\Models\Comment',
                'votable_id' => $reposteaCommentId,
                'value' => 1, // Favourites are always positive
                'type' => 'interesting', // All votes are interesting
                'created_at' => Carbon::parse($mbinFavourite->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::parse($mbinFavourite->created_at)->format('Y-m-d H:i:s'),
            ];

            $voteId = DB::table('votes')->insertGetId($voteData);

            return $voteId;

        } catch (Exception $e) {
            Log::error("Error creating comment vote from favourite (Mbin ID: {$mbinFavourite->id}): {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Synchronize post deletion/visibility status from Mbin to Repostea.
     * Only affects posts that already exist in Repostea (does not create new records).
     */
    private function syncPostsStatus(): void
    {
        $this->info('🔄 Synchronizing post deletion/visibility status...');
        Log::info('Starting posts status synchronization');

        try {
            $mbinEntries = DB::connection('mbin')->table('entry')
                ->select('id', 'visibility')
                ->get();

            $bar = $this->output->createProgressBar($mbinEntries->count());
            $bar->start();

            $deleted = 0;
            $restored = 0;
            $skipped = 0;

            foreach ($mbinEntries as $mbinEntry) {
                $reposteaPostId = MbinImportTracking::getReposteaId('entry', $mbinEntry->id);

                if (! $reposteaPostId) {
                    // Post does not exist in Repostea, skip
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $reposteaPost = DB::table('posts')
                    ->where('id', $reposteaPostId)
                    ->first();

                if (! $reposteaPost) {
                    // Post was permanently deleted from Repostea
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $isDeletedInMbin = $mbinEntry->visibility !== 'visible';
                $isDeletedInRepostea = $reposteaPost->deleted_at !== null;

                if ($isDeletedInMbin && ! $isDeletedInRepostea) {
                    // The post is deleted in Mbin but not in Repostea -> mark it as deleted
                    DB::table('posts')
                        ->where('id', $reposteaPostId)
                        ->update([
                            'deleted_at' => now()->format('Y-m-d H:i:s'),
                        ]);

                    $deleted++;
                    Log::info("Post {$reposteaPostId} (Mbin entry {$mbinEntry->id}) marked as deleted (visibility: {$mbinEntry->visibility})");
                } elseif (! $isDeletedInMbin && $isDeletedInRepostea) {
                    // The post is visible in Mbin but deleted in Repostea -> restore it
                    DB::table('posts')
                        ->where('id', $reposteaPostId)
                        ->update([
                            'deleted_at' => null,
                        ]);

                    $restored++;
                    Log::info("Post {$reposteaPostId} (Mbin entry {$mbinEntry->id}) restored (visibility: {$mbinEntry->visibility})");
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✅ Status synchronized: {$deleted} deleted, {$restored} restored, {$skipped} skipped");
            Log::info('Posts status synchronization completed', [
                'deleted' => $deleted,
                'restored' => $restored,
                'skipped' => $skipped,
            ]);
            $this->newLine();

        } catch (Exception $e) {
            $this->error("Error synchronizing posts status: {$e->getMessage()}");
            Log::error("Error in syncPostsStatus: {$e->getMessage()}");
        }
    }

    /**
     * Import magazine subscriptions.
     */
    private function importSubscriptions(bool $syncMode, bool $force, ?int $limit, ?int $hours): void
    {
        $this->info('🔖 Importing magazine subscriptions...');
        Log::info('Starting magazine subscriptions import', ['sync' => $syncMode, 'force' => $force, 'limit' => $limit, 'hours' => $hours]);

        try {
            $query = DB::connection('mbin')->table('magazine_subscription')
                ->orderBy('id');

            if ($hours) {
                $sinceDate = Carbon::now()->subHours($hours);
                $query->where('created_at', '>=', $sinceDate);
            }

            if ($limit) {
                $query->limit($limit);
            }

            $mbinSubscriptions = $query->get();
            $bar = $this->output->createProgressBar($mbinSubscriptions->count());
            $bar->start();

            $imported = 0;
            $skipped = 0;

            foreach ($mbinSubscriptions as $mbinSubscription) {
                if (! $force && MbinImportTracking::wasImported('magazine_subscription', $mbinSubscription->id)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $reposteaUserId = MbinImportTracking::getReposteaId('user', $mbinSubscription->user_id);
                $reposteaSubId = MbinImportTracking::getReposteaId('magazine', $mbinSubscription->magazine_id);

                if (! $reposteaUserId || ! $reposteaSubId) {
                    Log::warning("User or Magazine not found for subscription {$mbinSubscription->id}, skipping");
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                // Check if the subscription already exists
                $existingSubscription = DB::table('sub_subscriptions')
                    ->where('user_id', $reposteaUserId)
                    ->where('sub_id', $reposteaSubId)
                    ->first();

                if ($existingSubscription) {
                    MbinImportTracking::track('magazine_subscription', $mbinSubscription->id, $existingSubscription->id);
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                // Create subscription
                $subscriptionData = [
                    'user_id' => $reposteaUserId,
                    'sub_id' => $reposteaSubId,
                    'created_at' => Carbon::parse($mbinSubscription->created_at)->format('Y-m-d H:i:s'),
                    'updated_at' => Carbon::parse($mbinSubscription->created_at)->format('Y-m-d H:i:s'),
                ];

                $subscriptionId = DB::table('sub_subscriptions')->insertGetId($subscriptionData);

                MbinImportTracking::track('magazine_subscription', $mbinSubscription->id, $subscriptionId);
                $imported++;

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✅ Subscriptions imported: {$imported}, skipped: {$skipped}");
            Log::info('Magazine subscriptions import completed', ['imported' => $imported, 'skipped' => $skipped]);

            // Recalculate members_count for all subs
            $this->info('🔄 Recalculating members count for subs...');
            DB::statement('
                UPDATE subs s
                SET members_count = (
                    SELECT COUNT(*)
                    FROM sub_subscriptions ss
                    WHERE ss.sub_id = s.id
                )
            ');
            $this->info('✅ Members count updated');
            $this->newLine();

        } catch (Exception $e) {
            $this->error("Error importing subscriptions: {$e->getMessage()}");
            Log::error("Error in importSubscriptions: {$e->getMessage()}");
        }
    }

    /**
     * Import magazine moderators and owners.
     */
    private function importModerators(bool $syncMode, bool $force, ?int $limit, ?int $hours): void
    {
        $this->info('👮 Importing magazine moderators and owners...');
        Log::info('Starting magazine moderators import', ['sync' => $syncMode, 'force' => $force, 'limit' => $limit, 'hours' => $hours]);

        try {
            $query = DB::connection('mbin')->table('moderator')
                ->where('is_confirmed', true)
                ->orderBy('id');

            if ($hours) {
                $sinceDate = Carbon::now()->subHours($hours);
                $query->where('created_at', '>=', $sinceDate);
            }

            if ($limit) {
                $query->limit($limit);
            }

            $mbinModerators = $query->get();
            $bar = $this->output->createProgressBar($mbinModerators->count());
            $bar->start();

            $imported = 0;
            $skipped = 0;
            $ownersUpdated = 0;

            foreach ($mbinModerators as $mbinModerator) {
                if (! $force && MbinImportTracking::wasImported('moderator', $mbinModerator->id)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $reposteaUserId = MbinImportTracking::getReposteaId('user', $mbinModerator->user_id);
                $reposteaSubId = MbinImportTracking::getReposteaId('magazine', $mbinModerator->magazine_id);

                if (! $reposteaUserId || ! $reposteaSubId) {
                    Log::warning("User or Magazine not found for moderator {$mbinModerator->id}, skipping");
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                // If owner, update created_by field in subs
                if ($mbinModerator->is_owner) {
                    $sub = DB::table('subs')->where('id', $reposteaSubId)->first();

                    if ($sub && ! $sub->created_by) {
                        DB::table('subs')
                            ->where('id', $reposteaSubId)
                            ->update(['created_by' => $reposteaUserId]);

                        $ownersUpdated++;
                        Log::info("Sub {$reposteaSubId} owner set to user {$reposteaUserId}");
                    }
                }

                $existingModerator = DB::table('sub_moderators')
                    ->where('user_id', $reposteaUserId)
                    ->where('sub_id', $reposteaSubId)
                    ->first();

                if ($existingModerator) {
                    MbinImportTracking::track('moderator', $mbinModerator->id, $existingModerator->id);
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                // Get who added it
                $addedBy = null;
                if ($mbinModerator->added_by_user_id) {
                    $addedBy = MbinImportTracking::getReposteaId('user', $mbinModerator->added_by_user_id);
                }

                // Create moderator
                $moderatorData = [
                    'user_id' => $reposteaUserId,
                    'sub_id' => $reposteaSubId,
                    'is_owner' => $mbinModerator->is_owner,
                    'added_by' => $addedBy,
                    'created_at' => Carbon::parse($mbinModerator->created_at)->format('Y-m-d H:i:s'),
                    'updated_at' => Carbon::parse($mbinModerator->created_at)->format('Y-m-d H:i:s'),
                ];

                $moderatorId = DB::table('sub_moderators')->insertGetId($moderatorData);

                MbinImportTracking::track('moderator', $mbinModerator->id, $moderatorId);
                $imported++;

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✅ Moderators imported: {$imported}, skipped: {$skipped}, owners updated: {$ownersUpdated}");
            Log::info('Magazine moderators import completed', [
                'imported' => $imported,
                'skipped' => $skipped,
                'owners_updated' => $ownersUpdated,
            ]);
            $this->newLine();

        } catch (Exception $e) {
            $this->error("Error importing moderators: {$e->getMessage()}");
            Log::error("Error in importModerators: {$e->getMessage()}");
        }
    }
}
