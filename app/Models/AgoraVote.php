<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $agora_message_id
 * @property int $value
 * @property string|null $vote_type
 * @property string|null $fingerprint
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @property-read AgoraMessage $agoraMessage
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraVote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraVote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraVote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraVote whereAgoraMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraVote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraVote whereFingerprint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraVote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraVote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraVote whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraVote whereValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgoraVote whereVoteType($value)
 *
 * @mixin \Eloquent
 */
final class AgoraVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'agora_message_id',
        'value',
        'vote_type',
        'fingerprint',
    ];

    protected $casts = [
        'value' => 'integer',
    ];

    /**
     * Get the user that voted.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the message that was voted on.
     */
    public function agoraMessage(): BelongsTo
    {
        return $this->belongsTo(AgoraMessage::class);
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        self::created(function (AgoraVote $vote): void {
            $vote->agoraMessage->updateVotesCount();
        });

        self::updated(function (AgoraVote $vote): void {
            $vote->agoraMessage->updateVotesCount();
        });

        self::deleted(function (AgoraVote $vote): void {
            $vote->agoraMessage->updateVotesCount();
        });
    }
}
