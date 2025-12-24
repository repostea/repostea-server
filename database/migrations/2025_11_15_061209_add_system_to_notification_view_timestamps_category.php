<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip if table doesn't exist
        if (! Schema::hasTable('notification_view_timestamps')) {
            return;
        }

        // Only modify column type for MySQL (SQLite doesn't support ENUM or MODIFY COLUMN)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE notification_view_timestamps MODIFY COLUMN category ENUM('posts', 'comments', 'mentions', 'achievements', 'system') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Skip if table doesn't exist
        if (! Schema::hasTable('notification_view_timestamps')) {
            return;
        }

        // Only modify column type for MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE notification_view_timestamps MODIFY COLUMN category ENUM('posts', 'comments', 'mentions', 'achievements') NOT NULL");
        }
    }
};
