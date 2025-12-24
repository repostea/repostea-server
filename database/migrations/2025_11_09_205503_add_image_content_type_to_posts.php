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
        // SQLite and MySQL have different approaches to ENUM
        // For MySQL, we modify the ENUM. For SQLite, the column is already flexible (TEXT with check constraint)

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE posts MODIFY COLUMN content_type ENUM('text', 'link', 'video', 'audio', 'poll', 'image') NOT NULL DEFAULT 'link'");
        }
        // For SQLite, no migration needed as it uses TEXT with check constraint that already allows any value
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE posts MODIFY COLUMN content_type ENUM('text', 'link', 'video', 'audio', 'poll') NOT NULL DEFAULT 'link'");
        }
        // For SQLite, no migration needed
    }
};
