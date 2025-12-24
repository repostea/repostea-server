<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Set Spanish as default locale for existing users without a locale
        DB::table('users')
            ->whereNull('locale')
            ->update(['locale' => 'es']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to null for users with Spanish locale
        // (only if needed for rollback)
        DB::table('users')
            ->where('locale', 'es')
            ->update(['locale' => null]);
    }
};
