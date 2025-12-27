<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    /**
     * Clean image URLs by removing hardcoded domain.
     *
     * Converts absolute URLs like:
     *   https://api.renegados.es/api/v1/images/HASH/medium
     * To relative paths:
     *   /api/v1/images/HASH/medium
     *
     * This allows the URLs to work in any environment.
     */
    public function up(): void
    {
        // Clean posts.thumbnail_url
        DB::table('posts')
            ->where('thumbnail_url', 'LIKE', '%api.renegados.es%')
            ->update([
                'thumbnail_url' => DB::raw("REPLACE(thumbnail_url, 'https://api.renegados.es', '')"),
            ]);

        // Clean users.avatar
        DB::table('users')
            ->where('avatar', 'LIKE', '%api.renegados.es%')
            ->update([
                'avatar' => DB::raw("REPLACE(avatar, 'https://api.renegados.es', '')"),
            ]);

        // Clean users.avatar_url
        DB::table('users')
            ->where('avatar_url', 'LIKE', '%api.renegados.es%')
            ->update([
                'avatar_url' => DB::raw("REPLACE(avatar_url, 'https://api.renegados.es', '')"),
            ]);

        // Clean subs.icon (in case any exist)
        DB::table('subs')
            ->where('icon', 'LIKE', '%api.renegados.es%')
            ->update([
                'icon' => DB::raw("REPLACE(icon, 'https://api.renegados.es', '')"),
            ]);
    }

    /**
     * This migration is not reversible as we don't know the original domain.
     */
    public function down(): void
    {
        // Cannot reverse - the domain could be different per environment
    }
};
