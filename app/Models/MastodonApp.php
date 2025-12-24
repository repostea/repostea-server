<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores OAuth app credentials registered with Mastodon instances.
 *
 * Each instance requires its own app registration.
 *
 * @property int $id
 * @property string $instance
 * @property string $client_id
 * @property string $client_secret
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MastodonApp newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MastodonApp newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MastodonApp query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MastodonApp whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MastodonApp whereClientSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MastodonApp whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MastodonApp whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MastodonApp whereInstance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MastodonApp whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class MastodonApp extends Model
{
    protected $fillable = [
        'instance',
        'client_id',
        'client_secret',
    ];

    protected $hidden = [
        'client_secret',
    ];
}
