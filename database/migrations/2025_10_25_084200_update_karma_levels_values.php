<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    public function up(): void
    {
        $updates = [
            50 => 200,      // Aprendiz
            200 => 1000,    // Colaborador
            500 => 4000,    // Experto
            1000 => 16000,  // Mentor
            2500 => 40000,  // Sabio
            5000 => 100000, // Leyenda
        ];

        foreach ($updates as $oldKarma => $newKarma) {
            DB::table('karma_levels')
                ->where('required_karma', $oldKarma)
                ->update(['required_karma' => $newKarma]);
        }
    }

    public function down(): void
    {
        $reverts = [
            200 => 50,      // Aprendiz
            1000 => 200,    // Colaborador
            4000 => 500,    // Experto
            16000 => 1000,  // Mentor
            40000 => 2500,  // Sabio
            100000 => 5000, // Leyenda
        ];

        foreach ($reverts as $currentKarma => $oldKarma) {
            DB::table('karma_levels')
                ->where('required_karma', $currentKarma)
                ->update(['required_karma' => $oldKarma]);
        }
    }
};
