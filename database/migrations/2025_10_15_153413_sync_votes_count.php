<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     * Synchronize votes_count field with actual vote counts from votes table.
     * The votes_count should only count positive votes (value = 1).
     */
    public function up(): void
    {
        // Synchronize posts votes_count
        DB::statement('
            UPDATE posts
            SET votes_count = (
                SELECT COUNT(*)
                FROM votes
                WHERE votes.votable_type = ?
                AND votes.votable_id = posts.id
                AND votes.value = 1
            )
        ', [Post::class]);

        // Synchronize comments votes_count
        DB::statement('
            UPDATE comments
            SET votes_count = (
                SELECT COUNT(*)
                FROM votes
                WHERE votes.votable_type = ?
                AND votes.votable_id = comments.id
                AND votes.value = 1
            )
        ', [Comment::class]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this data synchronization
    }
};
