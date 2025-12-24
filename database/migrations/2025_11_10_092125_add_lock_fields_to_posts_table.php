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
            $table->boolean('language_locked_by_admin')->default(false)->after('language_code');
            $table->boolean('nsfw_locked_by_admin')->default(false)->after('is_nsfw');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn(['language_locked_by_admin', 'nsfw_locked_by_admin']);
        });
    }
};
