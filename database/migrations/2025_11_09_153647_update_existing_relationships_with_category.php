<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing relationships with the appropriate category based on their type

        // Own content types: continuation, correction, update
        DB::table('post_relationships')
            ->whereIn('relationship_type', ['continuation', 'correction', 'update'])
            ->update(['relation_category' => 'own']);

        // External content types: reply, related, duplicate
        DB::table('post_relationships')
            ->whereIn('relationship_type', ['reply', 'related', 'duplicate'])
            ->update(['relation_category' => 'external']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Set all relation_category back to null (or default value)
        DB::table('post_relationships')
            ->update(['relation_category' => null]);
    }
};
