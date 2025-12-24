<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use phpseclib3\Crypt\RSA;

/**
 * ActivityPub Actor RSA Keys.
 *
 * Stores the RSA key pair for HTTP Signatures.
 *
 * @property int $id
 * @property int $actor_id
 * @property string $public_key
 * @property string $private_key
 * @property string $key_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ActivityPubActor $actor
 */
final class ActivityPubActorKey extends Model
{
    protected $table = 'activitypub_actor_keys';

    protected $fillable = [
        'actor_id',
        'public_key',
        'private_key',
        'key_id',
    ];

    protected $hidden = [
        'private_key',
    ];

    protected $casts = [
        'actor_id' => 'integer',
        'private_key' => 'encrypted',
    ];

    /**
     * Get the actor that owns these keys.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(ActivityPubActor::class, 'actor_id');
    }

    /**
     * Generate and save a new RSA key pair for an actor.
     */
    public static function generateForActor(ActivityPubActor $actor): self
    {
        // Generate RSA 2048 key pair
        $private = RSA::createKey(2048);
        $public = $private->getPublicKey();

        return self::create([
            'actor_id' => $actor->id,
            'public_key' => $public->toString('PKCS8'),
            'private_key' => $private->toString('PKCS8'),
            'key_id' => "{$actor->actor_uri}#main-key",
        ]);
    }

    /**
     * Ensure keys exist for an actor, generating if needed.
     */
    public static function ensureForActor(ActivityPubActor $actor): self
    {
        $keys = $actor->keys;

        if ($keys !== null) {
            return $keys;
        }

        return self::generateForActor($actor);
    }

    /**
     * Get the private key for signing.
     */
    public function getPrivateKeyForSigning(): RSA\PrivateKey
    {
        /** @var RSA\PrivateKey $key */
        $key = RSA::loadPrivateKey($this->private_key);

        return $key->withPadding(RSA::SIGNATURE_PKCS1);
    }
}
