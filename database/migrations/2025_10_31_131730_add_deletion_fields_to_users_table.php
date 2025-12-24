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
        // Add is_deleted first if it doesn't exist
        if (! Schema::hasColumn('users', 'is_deleted')) {
            Schema::table('users', static function (Blueprint $table): void {
                $table->boolean('is_deleted')->default(false)->after('is_guest');
            });
        }

        // Add deleted_at only if it doesn't exist (for tests)
        if (! Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', static function (Blueprint $table): void {
                $table->timestamp('deleted_at')->nullable();
            });
        }

        // Add deletion_number if it doesn't exist
        if (! Schema::hasColumn('users', 'deletion_number')) {
            Schema::table('users', static function (Blueprint $table): void {
                $table->unsignedInteger('deletion_number')->nullable()->unique();
            });
        }

        // Try to add index, ignore if it already exists
        try {
            Schema::table('users', static function (Blueprint $table): void {
                $table->index(['is_deleted', 'deleted_at']);
            });
        } catch (Exception $e) {
            // Index already exists, ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropIndex(['is_deleted', 'deleted_at']);
            $table->dropColumn(['is_deleted', 'deleted_at', 'deletion_number']);
        });
    }
};
