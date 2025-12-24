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
        // Fix icons that are missing 'fas fa-' prefix
        $iconMappings = [
            'edit' => 'fas fa-edit',
            'file-alt' => 'fas fa-file-alt',
            'newspaper' => 'fas fa-newspaper',
            'comment' => 'fas fa-comment',
            'comments' => 'fas fa-comments',
            'thumbs-up' => 'fas fa-thumbs-up',
            'vote-yea' => 'fas fa-vote-yea',
            'fire' => 'fas fa-fire',
            'star' => 'fas fa-star',
            'trophy' => 'fas fa-trophy',
            'crown' => 'fas fa-crown',
            'pen-fancy' => 'fas fa-pen-fancy',
            'check-circle' => 'fas fa-check-circle',
            'users' => 'fas fa-users',
            'gem' => 'fas fa-gem',
            // Fix emoji icons
            '💎' => 'fas fa-gem',
            '🌟' => 'fas fa-star',
            '🏆' => 'fas fa-trophy',
        ];

        foreach ($iconMappings as $oldIcon => $newIcon) {
            DB::table('achievements')
                ->where('icon', $oldIcon)
                ->update(['icon' => $newIcon]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the icon updates
        $iconMappings = [
            'fas fa-edit' => 'edit',
            'fas fa-file-alt' => 'file-alt',
            'fas fa-newspaper' => 'newspaper',
            'fas fa-comment' => 'comment',
            'fas fa-comments' => 'comments',
            'fas fa-thumbs-up' => 'thumbs-up',
            'fas fa-vote-yea' => 'vote-yea',
            'fas fa-fire' => 'fire',
            'fas fa-star' => 'star',
            'fas fa-trophy' => 'trophy',
            'fas fa-crown' => 'crown',
            'fas fa-pen-fancy' => 'pen-fancy',
            'fas fa-check-circle' => 'check-circle',
            'fas fa-users' => 'users',
            'fas fa-gem' => 'gem',
        ];

        foreach ($iconMappings as $newIcon => $oldIcon) {
            DB::table('achievements')
                ->where('icon', $newIcon)
                ->update(['icon' => $oldIcon]);
        }
    }
};
