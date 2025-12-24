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
        Schema::table('post_relationships', function (Blueprint $table): void {
            $table->integer('upvotes_count')->default(0)->after('notes');
            $table->integer('downvotes_count')->default(0)->after('upvotes_count');
            $table->integer('score')->default(0)->after('downvotes_count')->comment('upvotes - downvotes');

            // Index for ordering by score
            $table->index('score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_relationships', function (Blueprint $table): void {
            $table->dropIndex(['score']);
            $table->dropColumn(['upvotes_count', 'downvotes_count', 'score']);
        });
    }
};
