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
        Schema::create('subs', static function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique(); // Short name (slug)
            $table->string('display_name'); // Display name
            $table->text('description')->nullable();
            $table->text('rules')->nullable();
            $table->string('icon')->nullable(); // Emoji or icon
            $table->string('color')->default('#3B82F6'); // HEX color
            $table->integer('members_count')->default(0);
            $table->integer('posts_count')->default(0);
            $table->boolean('is_private')->default(false);
            $table->boolean('is_adult')->default(false);
            $table->enum('visibility', ['visible', 'hidden', 'private'])->default('visible');
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('visibility');
        });

        // Table for sub subscriptions
        Schema::create('sub_subscriptions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('sub_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'sub_id']);
        });

        // Table to relate posts with subs
        Schema::create('post_sub', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('sub_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['post_id', 'sub_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_sub');
        Schema::dropIfExists('sub_subscriptions');
        Schema::dropIfExists('subs');
    }
};
