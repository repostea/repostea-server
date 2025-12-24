<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $post_id
 * @property int|null $user_id
 * @property string $ip_address
 * @property string|null $user_agent
 * @property string|null $referer
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $utm_term
 * @property string|null $utm_content
 * @property string|null $screen_resolution
 * @property string|null $session_id
 * @property string|null $language
 * @property \Illuminate\Support\Carbon $visited_at
 * @property-read Post $post
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended wherePostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended whereReferer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended whereScreenResolution($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended whereSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended whereUtmCampaign($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended whereUtmContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended whereUtmMedium($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended whereUtmSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended whereUtmTerm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostViewExtended whereVisitedAt($value)
 *
 * @mixin \Eloquent
 */
final class PostViewExtended extends Model
{
    protected $table = 'post_views_extended';

    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'user_id',
        'ip_address',
        'user_agent',
        'referer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'screen_resolution',
        'session_id',
        'language',
        'visited_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
