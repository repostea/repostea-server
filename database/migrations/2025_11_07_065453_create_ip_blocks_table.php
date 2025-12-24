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
        if (Schema::hasTable('ip_blocks')) {
            return;
        }

        Schema::create('ip_blocks', function (Blueprint $table): void {
            $table->id();
            $table->string('ip_address')->index();
            $table->enum('type', ['single', 'range', 'pattern'])->default('single');
            $table->string('ip_range_start')->nullable();
            $table->string('ip_range_end')->nullable();
            $table->enum('block_type', ['temporary', 'permanent'])->default('permanent');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable(); // To store country, user_agent, etc.
            $table->integer('hit_count')->default(0); // Counter of blocked attempts
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_blocks');
    }
};
