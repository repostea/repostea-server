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
        // Add federation stats to posts
        Schema::table('posts', function (Blueprint $table): void {
            $table->unsignedInteger('federation_likes_count')->default(0)->after('votes_count');
            $table->unsignedInteger('federation_shares_count')->default(0)->after('federation_likes_count');
            $table->unsignedInteger('federation_replies_count')->default(0)->after('federation_shares_count');
        });

        // Create remote_users table for federated users
        Schema::create('remote_users', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_uri')->unique();
            $table->string('username');
            $table->string('domain');
            $table->string('display_name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('profile_url')->nullable();
            $table->string('software')->nullable(); // mastodon, lemmy, pleroma, misskey, etc.
            $table->json('metadata')->nullable(); // Extra data from actor document
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamps();

            $table->index(['username', 'domain']);
            $table->index('domain');
        });

        // Add remote user support to comments
        Schema::table('comments', function (Blueprint $table): void {
            $table->foreignId('remote_user_id')->nullable()->after('user_id')
                ->constrained('remote_users')->nullOnDelete();
            $table->string('source')->default('local')->after('status'); // local, mastodon, lemmy, etc.
            $table->string('source_uri')->nullable()->after('source'); // Original ActivityPub URI
        });

        // Make user_id nullable in comments (for remote comments)
        Schema::table('comments', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropForeign(['remote_user_id']);
            $table->dropColumn(['remote_user_id', 'source', 'source_uri']);
        });

        Schema::dropIfExists('remote_users');

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn([
                'federation_likes_count',
                'federation_shares_count',
                'federation_replies_count',
            ]);
        });
    }
};
