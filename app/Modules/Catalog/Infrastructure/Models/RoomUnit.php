<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Catalog\Domain\Room\BedConfig;
use App\Modules\Catalog\Domain\Room\RoomUnitStatus;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Database\Factories\RoomUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Physical room unit (e.g. "Villa 101", "Room 204").
 *
 * @property int $id
 * @property int $organization_id
 * @property int $property_id
 * @property int $room_type_id
 * @property string $number
 * @property string|null $floor
 * @property BedConfig $bed_config
 * @property RoomUnitStatus $status
 * @property bool $is_active
 * @property string|null $notes
 * @property RoomType|null $roomType
 */
class RoomUnit extends Model
{
    /** @use HasFactory<RoomUnitFactory> */
    use HasFactory;

    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'property_id',
        'room_type_id',
        'number',
        'floor',
        'bed_config',
        'status',
        'is_active',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bed_config' => BedConfig::class,
            'status' => RoomUnitStatus::class,
            'is_active' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
