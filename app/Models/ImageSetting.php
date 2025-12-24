<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string|null $image_type
 * @property string $size_name
 * @property int $width
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageSetting whereImageType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageSetting whereSizeName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImageSetting whereWidth($value)
 *
 * @mixin \Eloquent
 */
final class ImageSetting extends Model
{
    protected $connection = 'media';

    protected $fillable = [
        'image_type',
        'size_name',
        'width',
    ];

    protected $casts = [
        'width' => 'integer',
    ];

    /**
     * Get sizes for a specific image type.
     *
     * @return array<string, int> Array of size_name => width
     */
    public static function getSizesForType(string $imageType): array
    {
        $cacheKey = "image_settings_{$imageType}";

        return Cache::tags(['settings'])->remember($cacheKey, now()->addHours(24), static fn () => self::where('image_type', $imageType)
            ->pluck('width', 'size_name')
            ->toArray());
    }

    /**
     * Clear settings cache when settings are updated.
     */
    protected static function booted(): void
    {
        self::saved(static function (ImageSetting $setting): void {
            Cache::forget("image_settings_{$setting->image_type}");
        });

        self::deleted(static function (ImageSetting $setting): void {
            Cache::forget("image_settings_{$setting->image_type}");
        });
    }
}
