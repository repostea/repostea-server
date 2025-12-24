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
        if (Schema::hasColumn('post_views_extended', 'session_id')) {
            return;
        }

        Schema::table('post_views_extended', function (Blueprint $table): void {
            // Session ID for grouping visits from same browsing session
            $table->string('session_id', 100)->nullable()->after('screen_resolution');

            // Language preference from user (e.g., 'es', 'en', 'ca')
            $table->string('language', 10)->nullable()->after('session_id');

            // Indexes for analytics
            $table->index('session_id');
            $table->index('language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_views_extended', function (Blueprint $table): void {
            $table->dropIndex(['session_id']);
            $table->dropIndex(['language']);

            $table->dropColumn(['session_id', 'language']);
        });
    }
};
