<?php

declare(strict_types=1);

use App\Models\Achievement;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Delete old featured_collaborator achievement
        Achievement::where('slug', 'featured_collaborator')->delete();

        // Create three-tier collaborator achievements
        $achievements = [
            [
                'name' => 'achievements.collaborator_bronze_title',
                'slug' => 'collaborator_bronze',
                'description' => 'achievements.collaborator_bronze_description',
                'icon' => 'fas fa-code-branch',
                'type' => 'special',
                'requirements' => ['special' => 'collaborator_bronze'],
                'karma_bonus' => 50,
            ],
            [
                'name' => 'achievements.collaborator_silver_title',
                'slug' => 'collaborator_silver',
                'description' => 'achievements.collaborator_silver_description',
                'icon' => 'fas fa-code-branch',
                'type' => 'special',
                'requirements' => ['special' => 'collaborator_silver'],
                'karma_bonus' => 100,
            ],
            [
                'name' => 'achievements.collaborator_gold_title',
                'slug' => 'collaborator_gold',
                'description' => 'achievements.collaborator_gold_description',
                'icon' => 'fas fa-code-branch',
                'type' => 'special',
                'requirements' => ['special' => 'collaborator_gold'],
                'karma_bonus' => 200,
            ],
        ];

        foreach ($achievements as $achievement) {
            Achievement::updateOrCreate(
                ['slug' => $achievement['slug']],
                $achievement,
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete three-tier collaborator achievements
        Achievement::whereIn('slug', [
            'collaborator_bronze',
            'collaborator_silver',
            'collaborator_gold',
        ])->delete();

        // Restore old featured_collaborator achievement
        Achievement::updateOrCreate(
            ['slug' => 'featured_collaborator'],
            [
                'name' => 'achievements.featured_collaborator_title',
                'slug' => 'featured_collaborator',
                'description' => 'achievements.featured_collaborator_description',
                'icon' => 'fas fa-code-branch',
                'type' => 'special',
                'requirements' => ['count' => 1, 'action' => 'featured'],
                'karma_bonus' => 100,
            ],
        );
    }
};
