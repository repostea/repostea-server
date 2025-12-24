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
        // Insert relationship achievements
        DB::table('achievements')->insert([
            // Basic achievement - first step
            [
                'name' => 'Relacionista',
                'slug' => 'relationalist',
                'description' => 'Crea 2 relaciones entre posts',
                'icon' => 'fas fa-code-branch',
                'type' => 'action',
                'requirements' => json_encode([
                    'type' => 'relationships',
                    'count' => 2,
                    'min_score' => 0,
                ]),
                'karma_bonus' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Logros especiales de relaciones
            [
                'name' => 'Conexiones Valiosas',
                'slug' => 'valuable-connections',
                'description' => '10 relaciones con +10 votos cada una',
                'icon' => 'fas fa-heart',
                'type' => 'special',
                'requirements' => json_encode([
                    'type' => 'relationships',
                    'count' => 10,
                    'min_score' => 10,
                ]),
                'karma_bonus' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Guardián de Enlaces',
                'slug' => 'link-guardian',
                'description' => '50 relaciones con +8 votos cada una',
                'icon' => 'fas fa-shield-alt',
                'type' => 'special',
                'requirements' => json_encode([
                    'type' => 'relationships',
                    'count' => 50,
                    'min_score' => 8,
                ]),
                'karma_bonus' => 250,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Leyenda del Enlace',
                'slug' => 'link-legend',
                'description' => '100 relaciones con +10 votos cada una',
                'icon' => 'fas fa-crown',
                'type' => 'special',
                'requirements' => json_encode([
                    'type' => 'relationships',
                    'count' => 100,
                    'min_score' => 10,
                ]),
                'karma_bonus' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('achievements')->whereIn('slug', [
            'relationalist',
            'valuable-connections',
            'link-guardian',
            'link-legend',
        ])->delete();
    }
};
