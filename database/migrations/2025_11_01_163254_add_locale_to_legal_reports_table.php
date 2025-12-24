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
        // Skip if table doesn't exist
        if (! Schema::hasTable('legal_reports')) {
            return;
        }

        if (Schema::hasColumn('legal_reports', 'locale')) {
            return;
        }

        Schema::table('legal_reports', function (Blueprint $table): void {
            // Add locale field to store the language in which the report was made
            $table->string('locale', 5)->default('en')->after('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_reports', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
