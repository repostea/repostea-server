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
        if (! Schema::hasTable('legal_reports')) {
            return;
        }

        // Update any existing records that have 'reviewing' status to 'under_review'
        // This works for both MySQL and SQLite
        DB::table('legal_reports')
            ->where('status', 'reviewing')
            ->update(['status' => 'under_review']);

        // Only modify column type for MySQL (SQLite doesn't support ENUM or MODIFY COLUMN)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE legal_reports MODIFY COLUMN status ENUM('pending', 'under_review', 'resolved', 'rejected') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Skip if table doesn't exist
        if (! Schema::hasTable('legal_reports')) {
            return;
        }

        // Update any existing records back
        DB::table('legal_reports')
            ->where('status', 'under_review')
            ->update(['status' => 'reviewing']);

        // Only modify column type for MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE legal_reports MODIFY COLUMN status ENUM('pending', 'reviewing', 'resolved', 'rejected') DEFAULT 'pending'");
        }
    }
};
