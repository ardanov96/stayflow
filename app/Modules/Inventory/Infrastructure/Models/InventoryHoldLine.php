<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Models;

use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stay date covered by an inventory hold.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $hold_id
 * @property int $property_id
 * @property string $date
 * @property int $units
 */
class InventoryHoldLine extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'hold_id',
        'property_id',
        'date',
        'units',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'units' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<InventoryHold, $this>
     */
    public function hold(): BelongsTo
    {
        return $this->belongsTo(InventoryHold::class, 'hold_id');
    }
}
