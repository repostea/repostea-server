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
            $table->unsignedInteger('total_replies_count')->default(0)->after('replies_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agora_messages', function (Blueprint $table): void {
            $table->dropColumn('total_replies_count');
        });
    }
};
