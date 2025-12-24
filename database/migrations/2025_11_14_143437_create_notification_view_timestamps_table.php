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
        if (Schema::hasTable('notification_view_timestamps')) {
            return;
        }

        Schema::create('notification_view_timestamps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('category', ['posts', 'comments', 'mentions', 'achievements', 'system']);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();

            // Unique constraint: one row per user per category
            $table->unique(['user_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_view_timestamps');
    }
};
