<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // Karma levels table
        Schema::create('karma_levels', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->integer('required_karma');
            $table->string('badge')->nullable();
            $table->text('description')->nullable();
            $table->json('benefits')->nullable();
            $table->timestamps();
        });

        // User streaks table
        Schema::create('user_streaks', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('current_streak')->default(0);
            $table->integer('longest_streak')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->timestamps();
        });

        // Karma histories table
        Schema::create('karma_histories', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('amount');
            $table->string('source');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['source', 'source_id']);
        });

        // Add foreign key to users table
        Schema::table('users', static function (Blueprint $table): void {
            $table->foreign('highest_level_id')->references('id')->on('karma_levels')->onDelete('set null');
        });

        // Karma events table
        Schema::create('karma_events', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->text('description');
            $table->decimal('multiplier', 3, 1)->default(1.0);
            $table->boolean('is_active')->default(true);
            $table->datetime('start_at')->nullable();
            $table->datetime('end_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropForeign(['highest_level_id']);
        });

        Schema::dropIfExists('karma_events');
        Schema::dropIfExists('karma_histories');
        Schema::dropIfExists('user_streaks');
        Schema::dropIfExists('karma_levels');
    }
};
