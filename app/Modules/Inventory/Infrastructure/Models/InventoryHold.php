<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Models;

use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Database\Factories\InventoryHoldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * InventoryHold model.
 *
 * A temporary claim on inventory units. The units are held in
 * `inventory_days.held_units` until the hold is committed (sold),
 * released, or expired.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $property_id
 * @property int $room_type_id
 * @property string $check_in
 * @property string $check_out
 * @property int $units
 * @property string $status
 * @property string $idempotency_key
 * @property Carbon $expires_at
 * @property int|null $reservation_id
 * @property Carbon|null $committed_at
 * @property Carbon|null $released_at
 * @property Carbon|null $expired_at
 * @property string|null $release_reason
 */
class InventoryHold extends Model
{
    /** @use HasFactory<InventoryHoldFactory> */
    use HasFactory;

    use BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'property_id',
        'room_type_id',
        'check_in',
        'check_out',
        'units',
        'status',
        'idempotency_key',
        'expires_at',
        'reservation_id',
        'committed_at',
        'released_at',
        'expired_at',
        'release_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'check_in' => 'date:Y-m-d',
            'check_out' => 'date:Y-m-d',
            'units' => 'integer',
            'expires_at' => 'datetime',
            'committed_at' => 'datetime',
            'released_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<InventoryHoldLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryHoldLine::class, 'hold_id');
    }
}
