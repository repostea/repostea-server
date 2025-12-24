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
        Schema::table('posts', function (Blueprint $table): void {
            // Add moderation columns
            $table->foreignId('moderated_by')->nullable()->constrained('users')->onDelete('set null')->after('status');
            $table->text('moderation_reason')->nullable()->after('moderated_by');
            $table->timestamp('moderated_at')->nullable()->after('moderation_reason');

            // Add index for moderated posts
            $table->index(['status', 'moderated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropForeign(['moderated_by']);
            $table->dropColumn(['moderated_by', 'moderation_reason', 'moderated_at']);
            $table->dropIndex(['status', 'moderated_at']);
        });
    }
};
