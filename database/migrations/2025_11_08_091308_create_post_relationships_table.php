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
        if (Schema::hasTable('post_relationships')) {
            return;
        }

        Schema::create('post_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_post_id')->constrained('posts')->onDelete('cascade');
            $table->foreignId('target_post_id')->constrained('posts')->onDelete('cascade');
            $table->enum('relationship_type', [
                'reply',
                'continuation',
                'related',
                'update',
                'correction',
                'duplicate',
            ]);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->text('notes')->nullable(); // Optional notes about the relationship
            $table->timestamps();

            // Prevent duplicate relationships
            $table->unique(['source_post_id', 'target_post_id', 'relationship_type'], 'unique_post_relationship');

            // Indexes for performance
            $table->index('source_post_id');
            $table->index('target_post_id');
            $table->index('relationship_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_relationships');
    }
};
