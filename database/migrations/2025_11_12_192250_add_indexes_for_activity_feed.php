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
        // Add indexes for activity feed queries
        // Note: Using try-catch to handle cases where indexes might already exist

        try {
            Schema::table('posts', function (Blueprint $table): void {
                $table->index('created_at', 'posts_created_at_index');
            });
        } catch (Exception $e) {
            // Index might already exist
        }

        try {
            Schema::table('posts', function (Blueprint $table): void {
                $table->index('frontpage_at', 'posts_frontpage_at_index');
            });
        } catch (Exception $e) {
            // Index might already exist
        }

        try {
            Schema::table('comments', function (Blueprint $table): void {
                $table->index('created_at', 'comments_created_at_index');
            });
        } catch (Exception $e) {
            // Index might already exist
        }

        try {
            Schema::table('comments', function (Blueprint $table): void {
                $table->index(['post_id', 'created_at'], 'comments_post_id_created_at_index');
            });
        } catch (Exception $e) {
            // Index might already exist
        }

        try {
            Schema::table('votes', function (Blueprint $table): void {
                $table->index('created_at', 'votes_created_at_index');
            });
        } catch (Exception $e) {
            // Index might already exist
        }

        try {
            Schema::table('votes', function (Blueprint $table): void {
                $table->index(['votable_type', 'votable_id', 'created_at'], 'votes_votable_created_at_index');
            });
        } catch (Exception $e) {
            // Index might already exist
        }

        try {
            Schema::table('seal_marks', function (Blueprint $table): void {
                $table->index('created_at', 'seal_marks_created_at_index');
            });
        } catch (Exception $e) {
            // Index might already exist
        }

        try {
            Schema::table('seal_marks', function (Blueprint $table): void {
                $table->index(['markable_type', 'markable_id', 'created_at'], 'seal_marks_markable_created_at_index');
            });
        } catch (Exception $e) {
            // Index might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('posts', function (Blueprint $table): void {
                $table->dropIndex('posts_created_at_index');
            });
        } catch (Exception $e) {
            // Index might not exist
        }

        try {
            Schema::table('posts', function (Blueprint $table): void {
                $table->dropIndex('posts_frontpage_at_index');
            });
        } catch (Exception $e) {
            // Index might not exist
        }

        try {
            Schema::table('comments', function (Blueprint $table): void {
                $table->dropIndex('comments_created_at_index');
            });
        } catch (Exception $e) {
            // Index might not exist
        }

        try {
            Schema::table('comments', function (Blueprint $table): void {
                $table->dropIndex('comments_post_id_created_at_index');
            });
        } catch (Exception $e) {
            // Index might not exist
        }

        try {
            Schema::table('votes', function (Blueprint $table): void {
                $table->dropIndex('votes_created_at_index');
            });
        } catch (Exception $e) {
            // Index might not exist
        }

        try {
            Schema::table('votes', function (Blueprint $table): void {
                $table->dropIndex('votes_votable_created_at_index');
            });
        } catch (Exception $e) {
            // Index might not exist
        }

        try {
            Schema::table('seal_marks', function (Blueprint $table): void {
                $table->dropIndex('seal_marks_created_at_index');
            });
        } catch (Exception $e) {
            // Index might not exist
        }

        try {
            Schema::table('seal_marks', function (Blueprint $table): void {
                $table->dropIndex('seal_marks_markable_created_at_index');
            });
        } catch (Exception $e) {
            // Index might not exist
        }
    }
};
