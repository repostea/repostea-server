<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table): void {
            $table->unsignedInteger('quiet_hours_pending_count')->default(0)->after('snoozed_until');
            $table->timestamp('quiet_hours_last_summary_at')->nullable()->after('quiet_hours_pending_count');
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table): void {
            $table->dropColumn(['quiet_hours_pending_count', 'quiet_hours_last_summary_at']);
        });
    }
};
