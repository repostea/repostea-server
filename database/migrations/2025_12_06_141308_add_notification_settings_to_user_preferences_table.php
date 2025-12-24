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
        Schema::table('user_preferences', function (Blueprint $table): void {
            // Detailed notification preferences (JSON structure for granular control)
            $table->json('notification_preferences')->nullable()->after('push_notifications');

            // Digest settings
            $table->enum('digest_frequency', ['none', 'daily', 'weekly'])->default('none')->after('notification_preferences');
            $table->tinyInteger('digest_day')->nullable()->after('digest_frequency'); // 0-6 for day of week (0 = Sunday)
            $table->tinyInteger('digest_hour')->default(9)->after('digest_day'); // Hour to send digest (0-23)

            // Quiet hours settings
            $table->boolean('quiet_hours_enabled')->default(false)->after('digest_hour');
            $table->time('quiet_hours_start')->nullable()->after('quiet_hours_enabled');
            $table->time('quiet_hours_end')->nullable()->after('quiet_hours_start');
            $table->string('timezone', 50)->default('Europe/Madrid')->after('quiet_hours_end');

            // Snooze (temporary silence)
            $table->timestamp('snoozed_until')->nullable()->after('timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table): void {
            $table->dropColumn([
                'notification_preferences',
                'digest_frequency',
                'digest_day',
                'digest_hour',
                'quiet_hours_enabled',
                'quiet_hours_start',
                'quiet_hours_end',
                'timezone',
                'snoozed_until',
            ]);
        });
    }
};
