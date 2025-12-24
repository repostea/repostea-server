<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     * Fix posts that have type='article' but content_type='link'.
     * All articles should have content_type='text' to ensure proper filtering.
     */
    public function up(): void
    {
        // Update all posts where type='article' but content_type is not 'text'
        DB::table('posts')
            ->where('type', 'article')
            ->where('content_type', '!=', 'text')
            ->update(['content_type' => 'text']);
    }

    /**
     * Reverse the migrations.
     *
     * Restore original content_type='link' for posts that were changed.
     * Note: This assumes these posts originally had content_type='link'.
     */
    public function down(): void
    {
        // Revert posts that have type='article' and content_type='text' back to 'link'
        // Only revert those that don't have any URL (true text articles should stay as text)
        DB::table('posts')
            ->where('type', 'article')
            ->where('content_type', 'text')
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->update(['content_type' => 'link']);
    }
};
