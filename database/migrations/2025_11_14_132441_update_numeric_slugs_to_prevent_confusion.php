<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     * Update posts with numeric-only slugs to add 'post-' prefix
     * This prevents confusion between IDs and slugs.
     */
    public function up(): void
    {
        // Get all posts and filter in PHP (SQLite doesn't support REGEXP)
        $allPosts = DB::table('posts')->get();

        // Filter posts with numeric-only slugs using PHP
        $posts = $allPosts->filter(fn ($post) => preg_match('/^\d+$/', $post->slug));

        // echo "Found {$posts->count()} posts with numeric-only slugs\n";

        foreach ($posts as $post) {
            $oldSlug = $post->slug;
            $newSlug = 'post-' . $oldSlug;

            // Check if new slug already exists
            $exists = DB::table('posts')
                ->where('slug', $newSlug)
                ->where('id', '!=', $post->id)
                ->exists();

            // If exists, add counter
            $counter = 1;
            $finalSlug = $newSlug;
            while ($exists) {
                $finalSlug = $newSlug . '-' . $counter;
                $exists = DB::table('posts')
                    ->where('slug', $finalSlug)
                    ->where('id', '!=', $post->id)
                    ->exists();
                $counter++;
            }

            // Update the slug
            DB::table('posts')
                ->where('id', $post->id)
                ->update(['slug' => $finalSlug]);

            // echo "Updated post {$post->id}: '{$oldSlug}' → '{$finalSlug}'\n";
        }

        // echo "Migration completed successfully!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'post-' prefix from slugs that were added by this migration
        $posts = DB::table('posts')
            ->where('slug', 'LIKE', 'post-%')
            ->get();

        foreach ($posts as $post) {
            $oldSlug = $post->slug;
            $newSlug = preg_replace('/^post-/', '', $oldSlug);

            // Only revert if the result is numeric
            if (preg_match('/^\d+(-\d+)?$/', $newSlug)) {
                DB::table('posts')
                    ->where('id', $post->id)
                    ->update(['slug' => $newSlug]);

                // echo "Reverted post {$post->id}: '{$oldSlug}' → '{$newSlug}'\n";
            }
        }
    }
};
