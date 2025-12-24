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
        // Skip if table doesn't exist or columns already exist
        if (! Schema::hasTable('legal_reports')) {
            return;
        }

        if (Schema::hasColumn('legal_reports', 'user_response')) {
            return;
        }

        Schema::table('legal_reports', function (Blueprint $table): void {
            // Public response that will be shown to the user when they check status
            $table->text('user_response')->nullable()->after('admin_notes');
            $table->timestamp('response_sent_at')->nullable()->after('user_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_reports', function (Blueprint $table): void {
            $table->dropColumn(['user_response', 'response_sent_at']);
        });
    }
};
