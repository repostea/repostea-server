<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     * These indexes optimize the N+1 query fixes implemented in:
     * - KarmaService: Bulk loading of vote/comment counts
     * - CommentController: Bulk loading of user votes on comments
     * - PostService: Bulk counting of new comments per post
     */
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            // Optimize: PostService->getNewCommentsCount()
            // Query: WHERE post_id = ? AND created_at > ? AND user_id != ?
            // Note: post_id_created_at already exists, but this adds user_id for better filtering
            $table->index(['post_id', 'user_id', 'created_at'], 'comments_post_user_created_index');
        });

        Schema::table('post_views', function (Blueprint $table): void {
            // Optimize: PostService->attachUserVisitInfo()
            // Query: WHERE user_id = ? AND post_id IN (...) - Already covered by post_user_unique
            // But add last_visited_at for future queries that might filter by it
            $table->index(['user_id', 'last_visited_at'], 'post_views_user_last_visited_index');
        });

        // Note: votes table already has optimal indexes:
        // - votes_user_id_votable_id_votable_type_unique (covers user + votable lookups)
        // - votes_votable_type_votable_id_index (covers votable lookups)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropIndex('comments_post_user_created_index');
        });

        Schema::table('post_views', function (Blueprint $table): void {
            $table->dropIndex('post_views_user_last_visited_index');
        });
    }
};
