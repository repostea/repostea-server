<?php

declare(strict_types=1);

use App\Models\Achievement;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     * Fix invalid Font Awesome icons for vote achievements.
     * 'fas fa-vote-yea' doesn't exist in FA6, use 'fa6-solid:check-to-slot' instead.
     */
    public function up(): void
    {
        // Fix votes_100 icon (fas fa-vote-yea doesn't exist in FA6)
        Achievement::where('icon', 'fas fa-vote-yea')
            ->update(['icon' => 'fa6-solid:check-to-slot']);

        // Also fix any other old FA5 format icons for vote achievements
        Achievement::where('icon', 'like', 'fas fa-%')
            ->where('slug', 'like', 'votes_%')
            ->update(['icon' => 'fa6-solid:thumbs-up']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Achievement::where('slug', 'votes_100')
            ->update(['icon' => 'fas fa-vote-yea']);

        Achievement::whereIn('slug', ['votes_10', 'votes_50'])
            ->update(['icon' => 'fas fa-thumbs-up']);
    }
};
