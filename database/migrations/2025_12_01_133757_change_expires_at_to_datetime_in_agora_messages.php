<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     * Change expires_at from TIMESTAMP to DATETIME to support dates beyond 2038.
     * TIMESTAMP has a max of 2038-01-19, DATETIME supports up to 9999-12-31.
     */
    public function up(): void
    {
        Schema::table('agora_messages', function (Blueprint $table): void {
            $table->dateTime('expires_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agora_messages', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->change();
        });
    }
};
