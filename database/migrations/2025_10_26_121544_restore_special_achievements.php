<?php

declare(strict_types=1);

use App\Models\Achievement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Restore the 3 special achievements that were removed
        $achievements = [
            [
                'name' => 'achievements.welcome_title',
                'slug' => 'welcome',
                'description' => 'achievements.welcome_description',
                'icon' => 'hand',
                'type' => 'special',
                'requirements' => ['count' => 1, 'action' => 'register'],
                'karma_bonus' => 5,
            ],
            [
                'name' => 'achievements.early_adopter_title',
                'slug' => 'early_adopter',
                'description' => 'achievements.early_adopter_description',
                'icon' => 'fas fa-rocket',
                'type' => 'special',
                'requirements' => ['count' => 1, 'action' => 'early_registration'],
                'karma_bonus' => 50,
            ],
            [
                'name' => 'achievements.featured_collaborator_title',
                'slug' => 'featured_collaborator',
                'description' => 'achievements.featured_collaborator_description',
                'icon' => 'fas fa-code-branch',
                'type' => 'special',
                'requirements' => ['count' => 1, 'action' => 'featured'],
                'karma_bonus' => 100,
            ],
        ];

        foreach ($achievements as $achievement) {
            Achievement::create($achievement);
        }

        // Auto-unlock "Bienvenido" for all existing users
        $welcomeAchievement = Achievement::where('name', 'achievements.welcome_title')->first();
        if ($welcomeAchievement) {
            // Use DB::table() to avoid SoftDeletes trait issues
            $users = DB::table('users')->select('id')->get();
            foreach ($users as $user) {
                // Only attach if not already attached
                $exists = DB::table('achievement_user')
                    ->where('user_id', $user->id)
                    ->where('achievement_id', $welcomeAchievement->id)
                    ->exists();

                if (! $exists) {
                    DB::table('achievement_user')->insert([
                        'user_id' => $user->id,
                        'achievement_id' => $welcomeAchievement->id,
                        'unlocked_at' => now(),
                        'progress' => 100,
                    ]);
                }
            }
        }

        // Auto-unlock "Early Adopter" for users registered before Jan 1, 2026
        $earlyAdopterAchievement = Achievement::where('name', 'achievements.early_adopter_title')->first();
        if ($earlyAdopterAchievement) {
            // Use DB::table() to avoid SoftDeletes trait issues
            $earlyUsers = DB::table('users')
                ->select('id')
                ->where('created_at', '<', '2026-01-01')
                ->get();
            foreach ($earlyUsers as $user) {
                $exists = DB::table('achievement_user')
                    ->where('user_id', $user->id)
                    ->where('achievement_id', $earlyAdopterAchievement->id)
                    ->exists();

                if (! $exists) {
                    DB::table('achievement_user')->insert([
                        'user_id' => $user->id,
                        'achievement_id' => $earlyAdopterAchievement->id,
                        'unlocked_at' => now(),
                        'progress' => 100,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Achievement::whereIn('name', [
            'achievements.welcome_title',
            'achievements.early_adopter_title',
            'achievements.featured_collaborator_title',
        ])->delete();
    }
};
