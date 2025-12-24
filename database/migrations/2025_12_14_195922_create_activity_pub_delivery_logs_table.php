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
        Schema::create('activity_pub_delivery_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('activitypub_actors')->cascadeOnDelete();
            $table->string('inbox_url', 500);
            $table->string('instance')->index();
            $table->string('activity_type', 50);
            $table->enum('status', ['success', 'failed', 'pending'])->default('pending');
            $table->integer('http_status')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempt_count')->default(1);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();

            $table->index(['instance', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_pub_delivery_logs');
    }
};
