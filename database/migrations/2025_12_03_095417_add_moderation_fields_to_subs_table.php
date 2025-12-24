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
        Schema::table('subs', function (Blueprint $table): void {
            $table->boolean('require_approval')->default(false)->after('is_featured');
            $table->json('allowed_content_types')->nullable()->after('require_approval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subs', function (Blueprint $table): void {
            $table->dropColumn(['require_approval', 'allowed_content_types']);
        });
    }
};
