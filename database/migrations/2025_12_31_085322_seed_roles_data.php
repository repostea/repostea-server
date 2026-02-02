<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates default roles and initial admin user.
     * Admin credentials come from .env (ADMIN_EMAIL, ADMIN_USERNAME, ADMIN_PASSWORD).
     */
    public function up(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'slug' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Full system access',
            ],
            [
                'name' => 'moderator',
                'slug' => 'moderator',
                'display_name' => 'Moderator',
                'description' => 'Can moderate content and comments',
            ],
            [
                'name' => 'user',
                'slug' => 'user',
                'display_name' => 'User',
                'description' => 'Regular user with basic permissions',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                $role,
            );
        }

        $this->createInitialAdminUser();
    }

    /**
     * Create initial admin user if no admin exists.
     */
    private function createInitialAdminUser(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();
        if (! $adminRole) {
            return;
        }

        // Check if ANY admin user exists (not a specific email)
        $adminExists = User::whereHas('roles', function ($query): void {
            $query->where('slug', 'admin');
        })->exists();

        if ($adminExists) {
            return;
        }

        // Create initial admin from .env credentials
        $user = User::create([
            'username' => env('ADMIN_USERNAME', 'admin'),
            'email' => env('ADMIN_EMAIL', 'admin@example.com'),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'changeme123')),
            'email_verified_at' => now(),
            'karma_points' => 5000,
        ]);

        $user->roles()->attach($adminRole);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't delete roles on rollback - they may have users attached
    }
};
