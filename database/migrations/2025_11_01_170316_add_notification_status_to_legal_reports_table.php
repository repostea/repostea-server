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

        if (Schema::hasColumn('legal_reports', 'notification_status')) {
            return;
        }

        Schema::table('legal_reports', function (Blueprint $table): void {
            // Track notification status: sending, sent, failed
            $table->enum('notification_status', ['sending', 'sent', 'failed'])->nullable();
            // Store error message if sending failed
            $table->text('notification_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_reports', function (Blueprint $table): void {
            $table->dropColumn(['notification_status', 'notification_error']);
        });
    }
};
