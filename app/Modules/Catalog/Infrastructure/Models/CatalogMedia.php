<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Optimized WebP media asset for properties or room types.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $property_id
 * @property string $mediable_type
 * @property int $mediable_id
 * @property string $disk
 * @property string $file_path
 * @property string $file_name
 * @property string $mime_type
 * @property int $file_size
 * @property int|null $width
 * @property int|null $height
 * @property array<string, string>|null $caption
 * @property int $sort_order
 * @property bool $is_primary
 */
class CatalogMedia extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $table = 'catalog_media';

    protected $fillable = [
        'organization_id',
        'property_id',
        'mediable_type',
        'mediable_id',
        'disk',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'width',
        'height',
        'caption',
        'sort_order',
        'is_primary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'caption' => 'array',
            'is_primary' => 'boolean',
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
