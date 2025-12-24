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
        // Add indexes for karma rankings
        Schema::table('users', function (Blueprint $table): void {
            // Only add if not exists
            if (! Schema::hasIndex('users', 'users_karma_points_index')) {
                $table->index('karma_points');
            }
            if (! Schema::hasIndex('users', 'users_is_guest_index')) {
                $table->index('is_guest');
            }
        });

        // Add indexes for karma history timeframe queries
        Schema::table('karma_histories', function (Blueprint $table): void {
            if (! Schema::hasIndex('karma_histories', 'karma_histories_user_id_created_at_index')) {
                $table->index(['user_id', 'created_at']);
            }
        });

        // Add indexes for posts rankings
        Schema::table('posts', function (Blueprint $table): void {
            if (! Schema::hasIndex('posts', 'posts_user_id_created_at_index')) {
                $table->index(['user_id', 'created_at']);
            }
        });

        // Add indexes for comments rankings
        Schema::table('comments', function (Blueprint $table): void {
            if (! Schema::hasIndex('comments', 'comments_user_id_created_at_index')) {
                $table->index(['user_id', 'created_at']);
            }
        });

        // Add indexes for streaks rankings
        Schema::table('user_streaks', function (Blueprint $table): void {
            if (! Schema::hasIndex('user_streaks', 'user_streaks_longest_streak_index')) {
                $table->index('longest_streak');
            }
            if (! Schema::hasIndex('user_streaks', 'user_streaks_current_streak_index')) {
                $table->index('current_streak');
            }
        });

        // Add indexes for achievements rankings
        Schema::table('achievement_user', function (Blueprint $table): void {
            if (! Schema::hasIndex('achievement_user', 'achievement_user_user_id_unlocked_at_index')) {
                $table->index(['user_id', 'unlocked_at']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasIndex('users', 'users_karma_points_index')) {
                $table->dropIndex('users_karma_points_index');
            }
            if (Schema::hasIndex('users', 'users_is_guest_index')) {
                $table->dropIndex('users_is_guest_index');
            }
        });

        Schema::table('karma_histories', function (Blueprint $table): void {
            if (Schema::hasIndex('karma_histories', 'karma_histories_user_id_created_at_index')) {
                $table->dropIndex('karma_histories_user_id_created_at_index');
            }
        });

        Schema::table('posts', function (Blueprint $table): void {
            if (Schema::hasIndex('posts', 'posts_user_id_created_at_index')) {
                $table->dropIndex('posts_user_id_created_at_index');
            }
        });

        Schema::table('comments', function (Blueprint $table): void {
            if (Schema::hasIndex('comments', 'comments_user_id_created_at_index')) {
                $table->dropIndex('comments_user_id_created_at_index');
            }
        });

        Schema::table('user_streaks', function (Blueprint $table): void {
            if (Schema::hasIndex('user_streaks', 'user_streaks_longest_streak_index')) {
                $table->dropIndex('user_streaks_longest_streak_index');
            }
            if (Schema::hasIndex('user_streaks', 'user_streaks_current_streak_index')) {
                $table->dropIndex('user_streaks_current_streak_index');
            }
        });

        Schema::table('achievement_user', function (Blueprint $table): void {
            if (Schema::hasIndex('achievement_user', 'achievement_user_user_id_unlocked_at_index')) {
                $table->dropIndex('achievement_user_user_id_unlocked_at_index');
            }
        });
    }
};
