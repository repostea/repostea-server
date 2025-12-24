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
        Schema::table('users', function (Blueprint $table): void {
            $table->bigInteger('telegram_id')->nullable()->unique()->after('federated_account_created_at');
            $table->string('telegram_username')->nullable()->after('telegram_id');
            $table->string('telegram_photo_url')->nullable()->after('telegram_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['telegram_id', 'telegram_username', 'telegram_photo_url']);
        });
    }
};
