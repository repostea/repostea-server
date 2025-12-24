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
        Schema::create('saved_list_posts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saved_list_id')->constrained()->onDelete('cascade');
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->text('notes')->nullable(); // Optional notes for saved post
            $table->timestamps();

            // Indexes for efficient searches
            $table->index(['saved_list_id', 'post_id']);
            $table->index(['post_id', 'saved_list_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_list_posts');
    }
};
