<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

final class UserMakeModerator extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'user:make-moderator
                            {username : Username of the user to make moderator}
                            {--remove : Remove moderator role instead of adding it}';

    /**
     * The console command description.
     */
    protected $description = 'Assign or remove moderator role to/from a user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $username = $this->argument('username');
        $remove = $this->option('remove');

        // Find the user
        $user = User::where('username', $username)->first();

        if (! $user) {
            $this->error("User '{$username}' not found.");

            return self::FAILURE;
        }

        // Find the moderator role
        $moderatorRole = Role::where('slug', 'moderator')->first();

        if (! $moderatorRole) {
            $this->error('Moderator role not found in database.');

            return self::FAILURE;
        }

        if ($remove) {
            // Remove moderator role
            if (! $user->roles()->where('role_id', $moderatorRole->id)->exists()) {
                $this->warn("User '{$username}' is not a moderator.");

                return self::SUCCESS;
            }

            $user->roles()->detach($moderatorRole->id);
            $this->info("✓ Moderator role removed from user '{$username}'.");
        } else {
            // Add moderator role
            if ($user->roles()->where('role_id', $moderatorRole->id)->exists()) {
                $this->warn("User '{$username}' is already a moderator.");

                return self::SUCCESS;
            }

            $user->roles()->attach($moderatorRole->id);
            $this->info("✓ User '{$username}' is now a moderator.");
        }

        // Show current roles
        $roles = $user->roles->pluck('name')->toArray();
        $this->line("\nCurrent roles for '{$username}': " . implode(', ', $roles));

        return self::SUCCESS;
    }
}
