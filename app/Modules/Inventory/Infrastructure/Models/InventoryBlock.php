<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Models;

use App\Models\User;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Database\Factories\InventoryBlockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InventoryBlock model.
 *
 * Maintenance or manual room unit blocks across date ranges.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $property_id
 * @property int $room_type_id
 * @property string $start_date
 * @property string $end_date
 * @property int $units
 * @property string $reason
 * @property int|null $created_by
 */
class InventoryBlock extends Model
{
    /** @use HasFactory<InventoryBlockFactory> */
    use HasFactory;

    use BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'property_id',
        'room_type_id',
        'start_date',
        'end_date',
        'units',
        'reason',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'units' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
