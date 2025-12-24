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
        // Skip if table already exists
        if (Schema::hasTable('legal_report_notifications')) {
            return;
        }

        Schema::create('legal_report_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_report_id')->constrained('legal_reports')->cascadeOnDelete();
            $table->foreignId('sent_by')->constrained('users')->cascadeOnDelete();
            $table->string('locale', 5); // en, es
            $table->text('content'); // Snapshot of content sent
            $table->enum('status', ['sending', 'sent', 'failed'])->default('sending');
            $table->text('error_message')->nullable();
            $table->string('recipient_email');
            $table->timestamps();

            $table->index('legal_report_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_report_notifications');
    }
};
