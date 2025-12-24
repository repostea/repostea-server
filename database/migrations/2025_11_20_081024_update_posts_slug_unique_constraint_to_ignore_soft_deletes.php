<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the existing unique constraint
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropUnique('posts_slug_unique');
        });

        // Create a new unique index that only considers non-deleted rows
        // MySQL doesn't support filtered unique indexes directly, but we can work around it
        // by creating a unique index on (slug, deleted_at) where deleted rows will have different deleted_at values
        // and non-deleted rows will all have NULL, making them unique
        DB::statement('CREATE UNIQUE INDEX posts_slug_unique ON posts (slug, deleted_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the conditional unique index
        DB::statement('DROP INDEX posts_slug_unique ON posts');

        // Restore the original simple unique constraint
        Schema::table('posts', function (Blueprint $table): void {
            $table->unique('slug', 'posts_slug_unique');
        });
    }
};
