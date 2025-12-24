<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $code
 * @property int|null $created_by
 * @property int|null $used_by
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property int $max_uses
 * @property int $current_uses
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation available()
 * @method static \Database\Factories\InvitationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation valid()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereCurrentUses($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereMaxUses($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereUsedBy($value)
 *
 * @mixin \Eloquent
 */
final class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'created_by',
        'used_by',
        'expires_at',
        'used_at',
        'max_uses',
        'current_uses',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'max_uses' => 'integer',
        'current_uses' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * User who created the invitation.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who used the invitation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    /**
     * Generate a unique invitation code.
     */
    public static function generateCode(int $length = 16): string
    {
        do {
            $code = Str::random($length);
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Check if the invitation is valid.
     */
    public function isValid(): bool
    {
        // Not active
        if (! $this->is_active) {
            return false;
        }

        // Has expired
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // Has reached maximum uses
        if ($this->current_uses >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Mark invitation as used.
     */
    public function markAsUsed(int $userId): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        $this->current_uses++;

        // If it's the first use, set used_by and used_at
        if ($this->current_uses === 1) {
            $this->used_by = $userId;
            $this->used_at = now();
        }

        // If it reached maximum uses, deactivate
        if ($this->current_uses >= $this->max_uses) {
            $this->is_active = false;
        }

        return $this->save();
    }

    /**
     * Find a valid invitation by code.
     */
    public static function findValidByCode(string $code): ?self
    {
        $invitation = self::where('code', $code)->first();

        if (! $invitation || ! $invitation->isValid()) {
            return null;
        }

        return $invitation;
    }

    /**
     * Scope for active invitations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for valid invitations (active and not expired).
     */
    public function scopeValid($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->whereRaw('current_uses < max_uses');
    }

    /**
     * Scope for available invitations (valid and with remaining uses).
     */
    public function scopeAvailable($query)
    {
        return $query->valid();
    }
}
