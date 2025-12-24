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
        Schema::table('agora_messages', function (Blueprint $table): void {
            $table->unsignedBigInteger('root_id')->nullable()->after('parent_id');
            $table->index('root_id');
        });

        // Set root_id for existing messages
        // For top-level messages: root_id = id
        // For replies: root_id = parent's root_id (recursively)
        DB::statement('
            UPDATE agora_messages 
            SET root_id = id 
            WHERE parent_id IS NULL
        ');

        // Update replies to have the same root_id as their parent
        // We need to do this iteratively for nested replies
        $maxIterations = 10;
        $driver = DB::connection()->getDriverName();

        for ($i = 0; $i < $maxIterations; $i++) {
            if ($driver === 'mysql') {
                // MySQL requires JOIN syntax (can't subquery same table in UPDATE)
                $updated = DB::update('
                    UPDATE agora_messages m
                    INNER JOIN agora_messages parent ON parent.id = m.parent_id
                    SET m.root_id = parent.root_id
                    WHERE m.root_id IS NULL
                    AND m.parent_id IS NOT NULL
                    AND parent.root_id IS NOT NULL
                ');
            } else {
                // SQLite supports subquery syntax
                $updated = DB::update('
                    UPDATE agora_messages
                    SET root_id = (
                        SELECT parent.root_id
                        FROM agora_messages AS parent
                        WHERE parent.id = agora_messages.parent_id
                    )
                    WHERE root_id IS NULL
                    AND parent_id IS NOT NULL
                    AND EXISTS (
                        SELECT 1 FROM agora_messages AS parent
                        WHERE parent.id = agora_messages.parent_id
                        AND parent.root_id IS NOT NULL
                    )
                ');
            }

            if ($updated === 0) {
                break;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agora_messages', function (Blueprint $table): void {
            $table->dropIndex(['root_id']);
            $table->dropColumn('root_id');
        });
    }
};
