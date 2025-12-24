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
        if (Schema::hasTable('post_views_extended')) {
            return;
        }

        Schema::create('post_views_extended', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('ip_address', 45); // IPv6 support
            $table->text('user_agent')->nullable();
            $table->string('referer', 500)->nullable();
            $table->timestamp('visited_at')->useCurrent();

            // Indexes for performance
            $table->index('post_id');
            $table->index('user_id');
            $table->index('ip_address');
            $table->index('visited_at');
            $table->index(['post_id', 'user_id']);
            $table->index(['post_id', 'ip_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_views_extended');
    }
};
