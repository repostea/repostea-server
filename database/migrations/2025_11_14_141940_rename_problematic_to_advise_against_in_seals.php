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
        // Rename columns in posts table
        if (Schema::hasColumn('posts', 'problematic_seals_count')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->renameColumn('problematic_seals_count', 'advise_against_seals_count');
            });
        }

        // Rename columns in comments table
        if (Schema::hasColumn('comments', 'problematic_seals_count')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->renameColumn('problematic_seals_count', 'advise_against_seals_count');
            });
        }

        // Update seal_marks type values
        // Note: For SQLite, we just update the data. The column was already created
        // with the correct values in the original migration.
        // For MySQL, we need to update the enum.
        if (Schema::hasTable('seal_marks')) {
            $driver = DB::getDriverName();

            if ($driver === 'mysql') {
                // MySQL: Modify enum to allow both values, update data, then restrict enum
                DB::statement("ALTER TABLE seal_marks MODIFY COLUMN type ENUM('recommended', 'problematic', 'advise_against')");
                DB::table('seal_marks')->where('type', 'problematic')->update(['type' => 'advise_against']);
                DB::statement("ALTER TABLE seal_marks MODIFY COLUMN type ENUM('recommended', 'advise_against')");
            } else {
                // SQLite and others: Just update the data
                DB::table('seal_marks')->where('type', 'problematic')->update(['type' => 'advise_against']);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename columns back in posts table
        if (Schema::hasColumn('posts', 'advise_against_seals_count')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->renameColumn('advise_against_seals_count', 'problematic_seals_count');
            });
        }

        // Rename columns back in comments table
        if (Schema::hasColumn('comments', 'advise_against_seals_count')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->renameColumn('advise_against_seals_count', 'problematic_seals_count');
            });
        }

        // Update seal_marks type values back
        if (Schema::hasTable('seal_marks')) {
            $driver = DB::getDriverName();

            if ($driver === 'mysql') {
                // MySQL: Modify enum to allow both values, update data, then restrict enum
                DB::statement("ALTER TABLE seal_marks MODIFY COLUMN type ENUM('recommended', 'problematic', 'advise_against')");
                DB::table('seal_marks')->where('type', 'advise_against')->update(['type' => 'problematic']);
                DB::statement("ALTER TABLE seal_marks MODIFY COLUMN type ENUM('recommended', 'problematic')");
            } else {
                // SQLite and others: Just update the data
                DB::table('seal_marks')->where('type', 'advise_against')->update(['type' => 'problematic']);
            }
        }
    }
};
