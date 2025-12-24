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
        if (Schema::hasTable('spam_detections')) {
            return;
        }

        Schema::create('spam_detections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('content_type'); // 'post' or 'comment'
            $table->unsignedBigInteger('content_id');
            $table->string('detection_type'); // 'duplicate', 'rapid_fire', 'high_spam_score'
            $table->float('similarity')->nullable(); // For duplicate detection
            $table->integer('spam_score')->nullable();
            $table->string('risk_level')->nullable(); // low, medium, high, critical
            $table->json('reasons')->nullable(); // Array of reasons
            $table->json('metadata')->nullable(); // Additional data (duplicate_of_id, etc.)
            $table->boolean('reviewed')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->string('action_taken')->nullable(); // 'ignored', 'warned', 'banned', 'deleted'
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['content_type', 'content_id']);
            $table->index(['reviewed', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spam_detections');
    }
};
