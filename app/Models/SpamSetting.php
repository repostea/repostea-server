<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $key
 * @property string $value
 * @property string $type
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamSetting whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamSetting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamSetting whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpamSetting whereValue($value)
 *
 * @mixin \Eloquent
 */
final class SpamSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * Get a setting value by key with caching.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::tags(['settings'])->remember("spam_setting_{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            if (! $setting) {
                return $default;
            }

            return self::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Set a setting value by key.
     */
    public static function setValue(string $key, mixed $value): void
    {
        $setting = self::where('key', $key)->first();

        // Convert boolean to '1' or '0' for proper storage
        $stringValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        if ($setting) {
            $setting->update(['value' => $stringValue]);
        } else {
            self::create([
                'key' => $key,
                'value' => $stringValue,
                'type' => self::inferType($value),
            ]);
        }

        Cache::tags(['settings'])->forget("spam_setting_{$key}");
    }

    /**
     * Cast value based on type.
     */
    private static function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => $value === '1' || $value === 'true',
            'integer' => (int) $value,
            'float' => (float) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Infer type from value.
     */
    private static function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_array($value) => 'json',
            default => 'string',
        };
    }
}
