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
        Schema::create('agora_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('agora_message_id')->constrained()->onDelete('cascade');
            $table->integer('value')->default(1); // 1 for upvote, -1 for downvote
            $table->string('fingerprint', 64)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'agora_message_id']);
            $table->index('agora_message_id');
            $table->index('fingerprint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agora_votes');
    }
};
