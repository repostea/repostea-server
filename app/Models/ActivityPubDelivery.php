<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tracks activity deliveries to remote inboxes.
 *
 * @property int $id
 * @property string $activity_id
 * @property string $target_inbox
 * @property string $status
 * @property int $attempts
 * @property string|null $last_error
 * @property \Carbon\Carbon|null $delivered_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityPubDelivery newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityPubDelivery newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityPubDelivery query()
 *
 * @mixin \Eloquent
 */
final class ActivityPubDelivery extends Model
{
    protected $table = 'activitypub_deliveries';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const MAX_ATTEMPTS = 5;

    protected $fillable = [
        'activity_id',
        'target_inbox',
        'status',
        'attempts',
        'last_error',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * Mark as delivered.
     */
    public function markDelivered(): void
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * Mark as failed with error.
     */
    public function markFailed(string $error): void
    {
        $this->increment('attempts');
        $this->update([
            'status' => $this->attempts >= self::MAX_ATTEMPTS
                ? self::STATUS_FAILED
                : self::STATUS_PENDING,
            'last_error' => $error,
        ]);
    }

    /**
     * Check if delivery can be retried.
     */
    public function canRetry(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->attempts < self::MAX_ATTEMPTS;
    }
}
