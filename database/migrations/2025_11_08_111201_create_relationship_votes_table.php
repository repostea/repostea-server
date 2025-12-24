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
        if (Schema::hasTable('relationship_votes')) {
            return;
        }

        Schema::create('relationship_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('relationship_id')->constrained('post_relationships')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('vote')->comment('1 for upvote, -1 for downvote');
            $table->timestamps();

            // A user can only vote once per relationship
            $table->unique(['relationship_id', 'user_id']);

            // Indexes for fast queries
            $table->index('relationship_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relationship_votes');
    }
};
