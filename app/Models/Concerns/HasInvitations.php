<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use const PHP_INT_MAX;

use App\Models\Invitation;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trait for invitation-related functionality.
 *
 * @property int|null $invitation_limit
 * @property int|null $karma_points
 * @property bool $is_guest
 * @property \Illuminate\Support\Carbon|null $created_at
 */
trait HasInvitations
{
    /**
     * Invitations created by the user.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'created_by');
    }

    /**
     * Get user's invitation limit.
     * Uses custom limit if set, otherwise calculates based on karma.
     */
    public function getInvitationLimit(): int
    {
        if ($this->invitation_limit !== null) {
            return $this->invitation_limit;
        }

        if ($this->hasRole('admin') && config('invitations.admin_unlimited')) {
            return PHP_INT_MAX;
        }

        if ($this->hasRole('moderator')) {
            return config('invitations.moderator_limit', 50);
        }

        $karmaLimits = config('invitations.karma_limits', [0 => 5]);
        $karma = $this->karma_points ?? 0;
        $limit = config('invitations.default_limit', 5);

        foreach ($karmaLimits as $threshold => $karmaLimit) {
            if ($karma >= $threshold) {
                $limit = $karmaLimit;
            }
        }

        return $limit;
    }

    /**
     * Get number of invitations created by user.
     */
    public function getInvitationCount(): int
    {
        return $this->invitations()->count();
    }

    /**
     * Get number of remaining invitations.
     */
    public function getRemainingInvitations(): int
    {
        $limit = $this->getInvitationLimit();

        if ($limit === PHP_INT_MAX) {
            return PHP_INT_MAX;
        }

        return max(0, $limit - $this->getInvitationCount());
    }

    /**
     * Check if user can create an invitation.
     */
    public function canCreateInvitation(): array
    {
        if ($this->is_guest && ! config('invitations.allow_guest_invitations')) {
            return ['can' => false, 'reason' => 'Guest users cannot create invitations'];
        }

        if (config('invitations.require_verified_email') && ! $this->hasVerifiedEmail()) {
            return ['can' => false, 'reason' => 'Email verification required'];
        }

        $minAge = config('invitations.minimum_account_age_days', 0);
        if ($minAge > 0 && $this->created_at->diffInDays(now()) < $minAge) {
            return ['can' => false, 'reason' => "Account must be at least {$minAge} days old"];
        }

        $minKarma = config('invitations.minimum_karma', 0);
        if (($this->karma_points ?? 0) < $minKarma) {
            return ['can' => false, 'reason' => "Minimum {$minKarma} karma required"];
        }

        $remaining = $this->getRemainingInvitations();
        if ($remaining <= 0 && $remaining !== PHP_INT_MAX) {
            return ['can' => false, 'reason' => 'Invitation limit reached'];
        }

        return ['can' => true, 'reason' => null];
    }
}
