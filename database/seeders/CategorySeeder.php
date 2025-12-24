<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Tecnología',
                'icon' => 'fa-microchip',
                'subcategories' => [
                    ['name' => 'Programación', 'icon' => 'fa-code'],
                    ['name' => 'Inteligencia Artificial', 'icon' => 'fa-robot'],
                    ['name' => 'Hardware', 'icon' => 'fa-laptop'],
                    ['name' => 'Software', 'icon' => 'fa-window-maximize'],
                ],
            ],
            [
                'name' => 'Ciencia',
                'icon' => 'fa-flask',
                'subcategories' => [
                    ['name' => 'Astronomía', 'icon' => 'fa-star'],
                    ['name' => 'Biología', 'icon' => 'fa-dna'],
                    ['name' => 'Física', 'icon' => 'fa-atom'],
                    ['name' => 'Medicina', 'icon' => 'fa-heartbeat'],
                ],
            ],
            [
                'name' => 'Política',
                'icon' => 'fa-landmark',
                'subcategories' => [
                    ['name' => 'Internacional', 'icon' => 'fa-globe'],
                    ['name' => 'Nacional', 'icon' => 'fa-flag'],
                    ['name' => 'Economía', 'icon' => 'fa-chart-line'],
                ],
            ],
            [
                'name' => 'Cultura',
                'icon' => 'fa-book',
                'subcategories' => [
                    ['name' => 'Cine', 'icon' => 'fa-film'],
                    ['name' => 'Literatura', 'icon' => 'fa-book-open'],
                    ['name' => 'Música', 'icon' => 'fa-music'],
                    ['name' => 'Arte', 'icon' => 'fa-palette'],
                ],
            ],
            [
                'name' => 'Deportes',
                'icon' => 'fa-futbol',
                'subcategories' => [
                    ['name' => 'Fútbol', 'icon' => 'fa-futbol'],
                    ['name' => 'Baloncesto', 'icon' => 'fa-basketball-ball'],
                    ['name' => 'Tenis', 'icon' => 'fa-table-tennis'],
                    ['name' => 'Otros', 'icon' => 'fa-running'],
                ],
            ],
        ];

    }
}
