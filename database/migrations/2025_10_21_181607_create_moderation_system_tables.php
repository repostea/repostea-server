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
        // User Bans - Global site bans
        Schema::create('user_bans', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('banned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('type')->default('temporary'); // temporary, permanent, shadowban
            $table->text('reason');
            $table->text('internal_notes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index('expires_at');
        });

        // User Strikes - Sistema de infracciones acumulativas
        Schema::create('user_strikes', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('issued_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('type')->default('warning'); // warning, minor, major, critical
            $table->text('reason');
            $table->text('internal_notes')->nullable();
            $table->foreignId('related_post_id')->nullable()->constrained('posts')->onDelete('set null');
            $table->foreignId('related_comment_id')->nullable()->constrained('comments')->onDelete('set null');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index('type');
        });

        // Reports - Sistema de reportes de contenido
        Schema::create('reports', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('reported_user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('reportable_type'); // Post, Comment, User
            $table->unsignedBigInteger('reportable_id');
            $table->string('reason'); // spam, harassment, inappropriate, misinformation, etc
            $table->text('description')->nullable();
            $table->string('status')->default('pending'); // pending, reviewing, resolved, dismissed
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('moderator_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['reportable_type', 'reportable_id']);
            $table->index(['status', 'created_at']);
            $table->index('reported_by');
        });

        // Moderation Log - Record of all moderation actions
        Schema::create('moderation_logs', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('target_user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('action'); // ban, unban, strike, remove_strike, delete_post, unpublish_post, etc
            $table->string('target_type')->nullable(); // Post, Comment, User
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable(); // Additional data (duration, type, etc)
            $table->timestamp('created_at');

            $table->index('moderator_id');
            $table->index(['target_type', 'target_id']);
            $table->index('created_at');
        });

        // Sub Bans - Sub-specific bans (like magazine_ban in Mbin)
        Schema::create('sub_bans', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sub_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('banned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('reason');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['sub_id', 'user_id']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_bans');
        Schema::dropIfExists('moderation_logs');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('user_strikes');
        Schema::dropIfExists('user_bans');
    }
};
