<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $entity_type
 * @property int $mbin_id
 * @property int $repostea_id
 * @property \Illuminate\Support\Carbon $imported_at
 * @property \Illuminate\Support\Carbon|null $last_synced_at
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MbinImportTracking newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MbinImportTracking newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MbinImportTracking query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MbinImportTracking whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MbinImportTracking whereEntityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MbinImportTracking whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MbinImportTracking whereImportedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MbinImportTracking whereLastSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MbinImportTracking whereMbinId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MbinImportTracking whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MbinImportTracking whereReposteaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MbinImportTracking whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class MbinImportTracking extends Model
{
    protected $table = 'mbin_import_tracking';

    protected $fillable = [
        'entity_type',
        'mbin_id',
        'repostea_id',
        'imported_at',
        'last_synced_at',
        'metadata',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Check if an entity was already imported.
     */
    public static function wasImported(string $entityType, int $mbinId): bool
    {
        return self::where('entity_type', $entityType)
            ->where('mbin_id', $mbinId)
            ->exists();
    }

    /**
     * Get the Repostea ID for a Mbin entity.
     */
    public static function getReposteaId(string $entityType, int $mbinId): ?int
    {
        $record = self::where('entity_type', $entityType)
            ->where('mbin_id', $mbinId)
            ->first();

        return $record?->repostea_id;
    }

    /**
     * Register an import.
     */
    public static function track(string $entityType, int $mbinId, int $reposteaId, ?array $metadata = null): self
    {
        return self::updateOrCreate(
            [
                'entity_type' => $entityType,
                'mbin_id' => $mbinId,
            ],
            [
                'repostea_id' => $reposteaId,
                'imported_at' => now(),
                'last_synced_at' => now(),
                'metadata' => $metadata,
            ],
        );
    }
}
