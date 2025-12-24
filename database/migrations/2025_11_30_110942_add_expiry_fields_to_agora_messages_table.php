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
        Schema::table('agora_messages', function (Blueprint $table): void {
            // Expiry duration in hours (null = permanent, which won't be allowed for new threads)
            $table->unsignedInteger('expires_in_hours')->nullable()->after('edited_at');
            // from_first = count from thread creation, from_last = count from last message
            $table->enum('expiry_mode', ['from_first', 'from_last'])->default('from_last')->after('expires_in_hours');
            // Calculated expiry timestamp (updated when new replies are added if mode is from_last)
            $table->timestamp('expires_at')->nullable()->index()->after('expiry_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agora_messages', function (Blueprint $table): void {
            $table->dropColumn(['expires_in_hours', 'expiry_mode', 'expires_at']);
        });
    }
};
