<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Models;

use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Database\Factories\InventoryDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InventoryDay model.
 *
 * Pre-materialized daily unit counts per room type.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $property_id
 * @property int $room_type_id
 * @property string $date
 * @property int $total_units
 * @property int $held_units
 * @property int $sold_units
 * @property int $blocked_units
 * @property-read int $available_units
 */
class InventoryDay extends Model
{
    /** @use HasFactory<InventoryDayFactory> */
    use HasFactory;

    use BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'property_id',
        'room_type_id',
        'date',
        'total_units',
        'held_units',
        'sold_units',
        'blocked_units',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'total_units' => 'integer',
            'held_units' => 'integer',
            'sold_units' => 'integer',
            'blocked_units' => 'integer',
        ];
    }

    /**
     * Computed available units: total - held - sold - blocked.
     * Never stored in DB per docs/ARCHITECTURE.md §5.1.
     */
    public function getAvailableUnitsAttribute(): int
    {
        return max(0, $this->total_units - $this->held_units - $this->sold_units - $this->blocked_units);
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
