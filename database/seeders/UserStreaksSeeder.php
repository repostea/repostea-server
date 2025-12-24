<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserStreak;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

final class UserStreaksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->info('No hay usuarios disponibles para crear rachas.');

            return;
        }

        $this->command->info('Creating user streaks...');

        foreach ($users as $user) {
            // Check if user already has a registered streak
            $existingStreak = UserStreak::where('user_id', $user->id)->first();

            if ($existingStreak) {
                $this->command->info("User {$user->name} already has a registered streak.");

                continue;
            }

            // Determine a random streak for the user
            // Older users tend to have longer streaks
            $daysRegistered = Carbon::now()->diffInDays($user->created_at) + 1;
            $maxPossibleStreak = min($daysRegistered, 365); // Maximum 1 year streak

            // Most users will have low streaks, some will have high streaks
            $streakProbability = rand(1, 100);
            $currentStreak = 1; // By default everyone has at least 1 day

            if ($streakProbability > 90) {
                // 10% of users with very high streaks (50%-100% of registered time)
                $currentStreak = rand((int) ($maxPossibleStreak * 0.5), (int) $maxPossibleStreak);
            } elseif ($streakProbability > 70) {
                // 20% of users with medium streaks (20%-50% of registered time)
                $currentStreak = rand((int) ($maxPossibleStreak * 0.2), (int) ($maxPossibleStreak * 0.5));
            } elseif ($streakProbability > 40) {
                // 30% of users with low streaks (5%-20% of registered time)
                $currentStreak = rand((int) ($maxPossibleStreak * 0.05), (int) ($maxPossibleStreak * 0.2));
            } else {
                // 40% of users with very low streaks (1%-5% of registered time)
                $currentStreak = rand(1, max(1, (int) ($maxPossibleStreak * 0.05)));
            }

            // Longest streak could be greater than current (user may have lost their streak)
            $longestStreak = rand($currentStreak, max($currentStreak, min(365, (int) ($maxPossibleStreak * 1.5))));

            // Create streak for user
            UserStreak::create([
                'user_id' => $user->id,
                'current_streak' => $currentStreak,
                'longest_streak' => $longestStreak,
                'last_activity_date' => Carbon::now(), // Todos han estado activos hoy
            ]);

            $this->command->info("Creada racha para {$user->name}: actual {$currentStreak}, récord {$longestStreak} días");
        }
    }
}
