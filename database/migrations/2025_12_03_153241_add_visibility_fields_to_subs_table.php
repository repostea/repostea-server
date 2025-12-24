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
            $table->boolean('hide_owner')->default(false)->after('require_approval');
            $table->boolean('hide_moderators')->default(false)->after('hide_owner');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subs', function (Blueprint $table): void {
            $table->dropColumn(['hide_owner', 'hide_moderators']);
        });
    }
};
