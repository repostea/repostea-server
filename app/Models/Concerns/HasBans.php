<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\UserBan;
use App\Models\UserStrike;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trait for ban and strike functionality.
 */
trait HasBans
{
    public function bans(): HasMany
    {
        return $this->hasMany(UserBan::class);
    }

    public function strikes(): HasMany
    {
        return $this->hasMany(UserStrike::class);
    }

    public function isBanned(): bool
    {
        return $this->bans()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}
