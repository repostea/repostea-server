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
        // Only create the table if it doesn't exist
        if (! Schema::hasTable('transparency_stats')) {
            Schema::create('transparency_stats', function (Blueprint $table): void {
                $table->id();

                // General statistics
                $table->unsignedBigInteger('total_posts')->default(0);
                $table->unsignedBigInteger('total_users')->default(0);
                $table->unsignedBigInteger('total_comments')->default(0);
                $table->unsignedInteger('total_aggregated_sources')->default(0);

                // Moderation reports
                $table->unsignedInteger('reports_total')->default(0);
                $table->unsignedInteger('reports_processed')->default(0);
                $table->unsignedInteger('reports_pending')->default(0);
                $table->unsignedInteger('avg_response_hours')->default(0);

                // Moderation actions
                $table->unsignedInteger('content_removed')->default(0);
                $table->unsignedInteger('warnings_issued')->default(0);
                $table->unsignedInteger('users_suspended')->default(0);
                $table->unsignedInteger('appeals_total')->default(0);

                // Report types breakdown (JSON: {spam: 89, copyright: 34, ...})
                $table->json('report_types')->nullable();

                // Timestamp for when these stats were calculated
                $table->timestamp('calculated_at')->useCurrent();
                $table->timestamps();

                // Index for faster queries on calculated_at
                $table->index('calculated_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transparency_stats');
    }
};
