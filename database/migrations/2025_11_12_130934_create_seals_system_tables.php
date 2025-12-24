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
        // Skip if table already exists
        if (! Schema::hasTable('user_seals')) {
            // Table to store user seals (points)
            Schema::create('user_seals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->integer('available_seals')->default(0);
                $table->integer('total_earned')->default(0);
                $table->integer('total_used')->default(0);
                $table->timestamp('last_awarded_at')->nullable();
                $table->timestamps();

                $table->index('user_id');
            });
        }

        // Skip if table already exists
        if (! Schema::hasTable('seal_marks')) {
            // Table to store seal marks on content (badges)
            Schema::create('seal_marks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Who applied the seal
                $table->morphs('markable'); // Post or Comment
                $table->enum('type', ['recommended', 'advise_against']); // Type of seal
                $table->timestamp('expires_at'); // When this seal mark expires (30 days)
                $table->timestamps();

                // Prevent duplicate marks from same user on same content
                $table->unique(['user_id', 'markable_id', 'markable_type', 'type'], 'unique_seal_mark');
                $table->index(['markable_id', 'markable_type']);
                $table->index('expires_at');
            });
        }

        // Add seal counts to posts table if they don't exist
        if (! Schema::hasColumn('posts', 'recommended_seals_count')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->integer('recommended_seals_count')->default(0)->after('votes_count');
                $table->integer('advise_against_seals_count')->default(0)->after('recommended_seals_count');
            });
        }

        // Add seal counts to comments table if they don't exist
        if (! Schema::hasColumn('comments', 'recommended_seals_count')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->integer('recommended_seals_count')->default(0)->after('votes_count');
                $table->integer('advise_against_seals_count')->default(0)->after('recommended_seals_count');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropColumn(['recommended_seals_count', 'advise_against_seals_count']);
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn(['recommended_seals_count', 'advise_against_seals_count']);
        });

        Schema::dropIfExists('seal_marks');
        Schema::dropIfExists('user_seals');
    }
};
