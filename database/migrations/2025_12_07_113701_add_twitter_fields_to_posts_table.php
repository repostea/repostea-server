<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->timestamp('twitter_posted_at')->nullable()->after('published_at');
            $table->string('twitter_tweet_id')->nullable()->after('twitter_posted_at');

            $table->index('twitter_posted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex(['twitter_posted_at']);
            $table->dropColumn(['twitter_posted_at', 'twitter_tweet_id']);
        });
    }
};
