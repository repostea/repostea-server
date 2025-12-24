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
        if (Schema::hasTable('poll_votes')) {
            return;
        }

        Schema::create('poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->onDelete('cascade');
            $table->integer('option_number'); // 1, 2, 3, etc.
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('device_fingerprint')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('post_id');
            $table->index('user_id');
            $table->index('device_fingerprint');

            // Prevent duplicate votes: user can only vote once per option
            $table->unique(['post_id', 'option_number', 'user_id'], 'unique_user_vote');
            $table->unique(['post_id', 'option_number', 'device_fingerprint'], 'unique_anonymous_vote');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
    }
};
