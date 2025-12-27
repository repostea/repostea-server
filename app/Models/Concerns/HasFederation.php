<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\ActivityPubActor;
use App\Models\ActivityPubUserSettings;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Trait for federation-related functionality (Mastodon, ActivityPub, Telegram).
 *
 * @property string|null $federated_id
 * @property string|null $federated_instance
 * @property string|null $federated_username
 * @property int|null $telegram_id
 */
trait HasFederation
{
    /**
     * ActivityPub/Federation settings for this user.
     */
    public function activityPubSettings(): HasOne
    {
        return $this->hasOne(ActivityPubUserSettings::class);
    }

    /**
     * ActivityPub actor for this user (for multi-actor federation).
     */
    public function activityPubActor(): HasOne
    {
        return $this->hasOne(ActivityPubActor::class, 'entity_id')
            ->where('actor_type', 'user');
    }

    /**
     * Check if user is a federated user (logged in via Mastodon/Fediverse).
     */
    public function isFederated(): bool
    {
        return $this->federated_id !== null;
    }

    /**
     * Get the full federated handle (e.g., "@user@mastodon.social").
     */
    public function getFederatedHandleAttribute(): ?string
    {
        if (! $this->isFederated()) {
            return null;
        }

        return '@' . $this->federated_username . '@' . $this->federated_instance;
    }

    /**
     * Scope to filter only federated users.
     */
    public function scopeFederated($query)
    {
        return $query->whereNotNull('federated_id');
    }

    /**
     * Scope to filter only local (non-federated) users.
     */
    public function scopeLocal($query)
    {
        return $query->whereNull('federated_id');
    }

    /**
     * Find a user by their federated identity.
     */
    public static function findByFederatedId(string $federatedId): ?self
    {
        return self::where('federated_id', $federatedId)->first();
    }

    /**
     * Check if user logged in via Telegram.
     */
    public function isTelegramUser(): bool
    {
        return $this->telegram_id !== null;
    }

    /**
     * Scope to filter only Telegram users.
     */
    public function scopeTelegram($query)
    {
        return $query->whereNotNull('telegram_id');
    }

    /**
     * Check if user is a social login user (Mastodon, Telegram, etc.).
     */
    public function isSocialLoginUser(): bool
    {
        return $this->isFederated() || $this->isTelegramUser();
    }
}
