<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Events\KarmaLevelUp;
use App\Models\KarmaHistory;
use App\Models\KarmaLevel;
use App\Models\UserStreak;
use App\Notifications\KarmaLevelUp as KarmaLevelUpNotification;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Trait for karma-related functionality.
 *
 * @property int|null $karma_points
 * @property int|null $highest_level_id
 */
trait HasKarma
{
    public function streak(): HasOne
    {
        return $this->hasOne(UserStreak::class);
    }

    public function karmaHistory(): HasMany
    {
        return $this->hasMany(KarmaHistory::class);
    }

    public function calculateCurrentLevel(): ?KarmaLevel
    {
        return KarmaLevel::where('required_karma', '<=', $this->karma_points)
            ->orderBy('required_karma', 'desc')
            ->first();
    }

    public function updateKarma(int $points): self
    {
        $previousLevelId = $this->highest_level_id;

        $this->karma_points += $points;
        if ($this->karma_points < 0) {
            $this->karma_points = 0;
        }

        $currentLevel = $this->calculateCurrentLevel();

        if ($currentLevel && ($this->highest_level_id === null || $currentLevel->id > $this->highest_level_id)) {
            $this->highest_level_id = $currentLevel->id;

            $this->save();

            // Only send notification if it's not the initial level (karma 0) and level changed
            if ($previousLevelId !== $currentLevel->id && $currentLevel->required_karma > 0) {
                event(new KarmaLevelUp($this, $currentLevel));
                $this->notify(new KarmaLevelUpNotification($currentLevel));
            }

            return $this;
        }

        $this->save();

        return $this;
    }

    public function getBadge(): ?string
    {
        $currentLevel = $this->currentLevel()->first();

        return $currentLevel ? $currentLevel->badge : null;
    }

    public function getKarmaMultiplierAttribute(): float
    {
        $streak = $this->streak()->first();

        return $streak ? $streak->karma_multiplier : 1.0;
    }

    public function recordKarma(int $amount, string $source, $sourceId = null, $description = null): KarmaHistory
    {
        return KarmaHistory::record($this, $amount, $source, $sourceId, $description);
    }
}
