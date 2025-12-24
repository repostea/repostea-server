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
            $table->timestamp('orphaned_at')->nullable()->after('members_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subs', function (Blueprint $table): void {
            $table->dropColumn('orphaned_at');
        });
    }
};
