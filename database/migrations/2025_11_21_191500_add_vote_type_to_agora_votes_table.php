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
        Schema::table('agora_votes', function (Blueprint $table): void {
            $table->string('vote_type', 50)->nullable()->after('value');
            $table->index('vote_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agora_votes', function (Blueprint $table): void {
            $table->dropIndex(['vote_type']);
            $table->dropColumn('vote_type');
        });
    }
};
