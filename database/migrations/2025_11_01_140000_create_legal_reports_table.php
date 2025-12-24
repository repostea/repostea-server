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
        if (Schema::hasTable('legal_reports')) {
            return;
        }

        Schema::create('legal_reports', function (Blueprint $table): void {
            $table->id();

            // Reference number for tracking (e.g., REP-20251101-A3F2)
            $table->string('reference_number', 20)->unique();

            // Report type
            $table->enum('type', [
                'copyright',
                'illegal',
                'harassment',
                'privacy',
                'spam',
                'other',
            ]);

            // Content URL being reported
            $table->string('content_url');

            // Reporter information
            $table->string('reporter_name');
            $table->string('reporter_email');
            $table->string('reporter_organization')->nullable();

            // Report details
            $table->text('description');

            // Copyright-specific fields
            $table->string('original_url')->nullable();
            $table->text('ownership_proof')->nullable();

            // Legal declarations (checkboxes)
            $table->boolean('good_faith')->default(false);
            $table->boolean('accurate_info')->default(false);
            $table->boolean('authorized')->default(false); // For copyright

            // Status tracking
            $table->enum('status', ['pending', 'reviewing', 'resolved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // IP address for legal purposes
            $table->string('ip_address')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('reference_number');
            $table->index('status');
            $table->index('type');
            $table->index('created_at');
            $table->index('reporter_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_reports');
    }
};
