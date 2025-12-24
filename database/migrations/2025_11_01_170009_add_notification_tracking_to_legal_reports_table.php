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

        if (Schema::hasColumn('legal_reports', 'notification_sent_at')) {
            return;
        }

        Schema::table('legal_reports', function (Blueprint $table): void {
            // Track when notification email was sent
            $table->timestamp('notification_sent_at')->nullable();
            // Track who sent the notification
            $table->foreignId('notification_sent_by')->nullable()->constrained('users')->nullOnDelete();
            // Track the language used for the notification
            $table->string('notification_locale', 5)->nullable();
            // Store the exact content that was sent
            $table->text('notification_content')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_reports', function (Blueprint $table): void {
            $table->dropForeign(['notification_sent_by']);
            $table->dropColumn([
                'notification_sent_at',
                'notification_sent_by',
                'notification_locale',
                'notification_content',
            ]);
        });
    }
};
