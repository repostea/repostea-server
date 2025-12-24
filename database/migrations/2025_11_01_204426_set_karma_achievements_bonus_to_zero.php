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
        // Karma achievements should not give additional karma
        // You already reached that karma, you don't need more
        DB::table('achievements')
            ->where('type', 'karma')
            ->update(['karma_bonus' => 0]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurar valores originales si es necesario
        $karmaAchievements = [
            'karma_100' => 50,
            'karma_500' => 100,
            'karma_1000' => 200,
            'karma_5000' => 500,
            'karma_10000' => 1000,
            'karma_50000' => 5000,
        ];

        foreach ($karmaAchievements as $slug => $bonus) {
            DB::table('achievements')
                ->where('slug', $slug)
                ->update(['karma_bonus' => $bonus]);
        }
    }
};
