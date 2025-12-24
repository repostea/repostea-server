<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // Posts table
        Schema::create('posts', static function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('is_original')->default(false);
            $table->string('status')->default('published');
            $table->integer('votes_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->integer('views')->default(0);
            $table->string('source')->nullable();
            $table->string('language_code', 5)->default('es');

            // External import fields
            $table->boolean('is_external_import')->default(false);
            $table->string('external_id')->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_url')->nullable();
            $table->string('external_source')->nullable();
            $table->timestamp('original_published_at')->nullable();

            // Multimedia fields
            $table->enum('content_type', ['text', 'link', 'video', 'audio', 'poll'])->default('link');
            $table->json('media_metadata')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_provider')->nullable();

            // SEO and routing
            $table->string('slug')->unique()->nullable();
            $table->string('uuid')->unique();

            $table->timestamps();

            $table->index(['external_id', 'source_name']);
            $table->index('language_code');
            $table->index('content_type');
        });

        // Comments table
        Schema::create('comments', static function (Blueprint $table): void {
            $table->id();
            $table->text('content');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade');
            $table->integer('votes_count')->default(0);
            $table->timestamps();
        });

        // Post views table
        Schema::create('post_views', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'ip_address']);
            $table->index(['post_id', 'user_id']);
        });

        // Votes table
        Schema::create('votes', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->morphs('votable');
            $table->tinyInteger('value');
            $table->enum('type', [
                'didactic', 'interesting', 'elaborate', 'funny',
                'incomplete', 'irrelevant', 'false', 'outofplace',
            ]);
            $table->timestamps();

            $table->unique(['user_id', 'votable_id', 'votable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
        Schema::dropIfExists('post_views');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('posts');
    }
};
