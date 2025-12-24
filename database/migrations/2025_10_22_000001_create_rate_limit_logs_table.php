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
        Schema::create('rate_limit_logs', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('action', 50)->index(); // e.g., 'create_post', 'create_comment'
            $table->string('ip_address', 45)->index();
            $table->text('user_agent')->nullable();
            $table->integer('attempts'); // Number of attempts when violation occurred
            $table->integer('max_attempts'); // The limit that was exceeded
            $table->string('endpoint')->nullable(); // API endpoint hit
            $table->string('method', 10)->nullable(); // HTTP method (POST, GET, etc.)
            $table->json('metadata')->nullable(); // Additional context (headers, referer, etc.)
            $table->timestamp('created_at');

            // Indexes for efficient querying
            $table->index(['user_id', 'action', 'created_at']);
            $table->index(['ip_address', 'action', 'created_at']);
            $table->index('created_at'); // For cleanup/retention queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_limit_logs');
    }
};
