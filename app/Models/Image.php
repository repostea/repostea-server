<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $hash
 * @property string $type
 * @property string|null $uploadable_type
 * @property int|null $uploadable_id
 * @property string|null $small_blob
 * @property string|null $medium_blob
 * @property string|null $large_blob
 * @property int|null $original_width
 * @property int|null $original_height
 * @property int|null $file_size
 * @property string|null $mime_type
 * @property bool $is_nsfw
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Model|null $uploadable
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereFileSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereIsNsfw($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereLargeBlob($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereMediumBlob($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereOriginalHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereOriginalWidth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereSmallBlob($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereUploadableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereUploadableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Image withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class Image extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $connection = 'media';

    protected $fillable = [
        'hash',
        'type',
        'uploadable_type',
        'uploadable_id',
        'small_blob',
        'medium_blob',
        'large_blob',
        'original_width',
        'original_height',
        'file_size',
        'mime_type',
        'is_nsfw',
        'user_id',
    ];

    protected $casts = [
        'original_width' => 'integer',
        'original_height' => 'integer',
        'file_size' => 'integer',
        'is_nsfw' => 'boolean',
    ];

    protected $hidden = [
        'small_blob',
        'medium_blob',
        'large_blob',
    ];

    /**
     * Get the owner of the image (User, Post, Comment).
     */
    public function uploadable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who uploaded the image.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get URL for the image.
     *
     * Size parameter kept for backward compatibility but all sizes
     * serve the same image (IPX handles resizing on frontend).
     */
    public function getUrl(string $size = 'medium'): string
    {
        // Size in URL kept for backward compatibility with existing references
        return url("/api/v1/images/{$this->hash}/{$size}");
    }

    /**
     * Get all URLs as array.
     *
     * All sizes return the same image (IPX handles resizing on frontend).
     */
    public function getUrls(): array
    {
        return [
            'small' => $this->getUrl('small'),
            'medium' => $this->getUrl('medium'),
            'large' => $this->getUrl('large'),
            'is_nsfw' => $this->is_nsfw,
        ];
    }

    /**
     * Get binary data for the image.
     *
     * New uploads only have large_blob (IPX handles resizing on frontend).
     * For backward compatibility, falls back to available sizes for old images.
     */
    public function getBlob(string $size = 'medium'): ?string
    {
        // Priority: large (new single-size uploads) -> requested size -> any available
        return $this->large_blob
            ?? $this->medium_blob
            ?? $this->small_blob;
    }
}
