<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            // Method: 'manual' (admin clicked button) or 'auto' (scheduled job)
            $table->string('twitter_post_method', 20)->nullable()->after('twitter_tweet_id');
            // Reason for auto-posting: 'popular_votes', 'original_article', etc.
            $table->string('twitter_post_reason', 50)->nullable()->after('twitter_post_method');
            // Admin who manually posted (null if auto)
            $table->foreignId('twitter_posted_by')->nullable()->after('twitter_post_reason')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropForeign(['twitter_posted_by']);
            $table->dropColumn(['twitter_post_method', 'twitter_post_reason', 'twitter_posted_by']);
        });
    }
};
