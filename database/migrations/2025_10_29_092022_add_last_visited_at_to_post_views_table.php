<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add last_visited_at column (only if it doesn't exist)
        if (! Schema::hasColumn('post_views', 'last_visited_at')) {
            Schema::table('post_views', function (Blueprint $table): void {
                $table->timestamp('last_visited_at')->nullable()->after('user_agent');
            });
        }

        // Step 2: Remove duplicates - keep only the most recent view per user/post
        // For authenticated users (user_id IS NOT NULL), keep the newest record
        // This runs even if column exists to clean up any duplicates
        // Using database-agnostic approach that works with both MySQL and SQLite
        $duplicateIds = DB::table('post_views as v1')
            ->select('v1.id')
            ->join(DB::raw('(
                SELECT post_id, user_id, MAX(id) as max_id
                FROM post_views
                WHERE user_id IS NOT NULL
                GROUP BY post_id, user_id
                HAVING COUNT(*) > 1
            ) as v2'), function ($join): void {
                $join->on('v1.post_id', '=', 'v2.post_id')
                    ->on('v1.user_id', '=', 'v2.user_id')
                    ->whereRaw('v1.id < v2.max_id');
            })
            ->pluck('id');

        if ($duplicateIds->isNotEmpty()) {
            DB::table('post_views')->whereIn('id', $duplicateIds)->delete();
        }

        // Step 3: Drop old index and create unique constraint (only if not already created)
        // Use Laravel's Schema Builder which works across database drivers
        try {
            Schema::table('post_views', function (Blueprint $table): void {
                // Try to drop old index if it exists
                try {
                    $table->dropIndex(['post_id', 'user_id']);
                } catch (Exception $e) {
                    // Index doesn't exist, that's fine
                }

                // Create unique constraint for post_id + user_id
                // This prevents duplicate views from same user
                $table->unique(['post_id', 'user_id'], 'post_user_unique');
            });
        } catch (Exception $e) {
            // Unique constraint already exists, that's fine
            // Or some other error occurred, but we can safely continue
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_views', function (Blueprint $table): void {
            // Remove unique constraint and restore original index
            $table->dropUnique('post_user_unique');
            $table->index(['post_id', 'user_id']);

            // Remove the last_visited_at column
            $table->dropColumn('last_visited_at');
        });
    }
};
