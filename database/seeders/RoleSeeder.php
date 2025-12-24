<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

final class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'slug' => 'admin',
                'display_name' => 'Administrador',
                'description' => 'Acceso total al sistema',
            ],
            [
                'name' => 'moderator',
                'slug' => 'moderator',
                'display_name' => 'Moderador',
                'description' => 'Puede moderar contenido y comentarios',
            ],
            [
                'name' => 'user',
                'slug' => 'user',
                'display_name' => 'Usuario',
                'description' => 'Usuario regular con permisos básicos',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']], // Find by name
                $role, // Create with all data
            );
        }
    }
}
