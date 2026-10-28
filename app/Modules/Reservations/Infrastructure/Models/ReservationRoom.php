<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Infrastructure\Models;

use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One room of a reservation.
 *
 * @property int $id
 * @property int $reservation_id
 * @property int $room_type_id
 * @property int|null $rate_plan_id
 * @property int|null $room_unit_id
 * @property array<int, array{date: string, price_minor: int}> $nights
 * @property int $total_minor
 * @property string $currency
 */
class ReservationRoom extends Model
{
    use BelongsToTenant;

    protected $table = 'reservation_rooms';

    protected $fillable = [
        'organization_id',
        'reservation_id',
        'property_id',
        'room_type_id',
        'rate_plan_id',
        'room_unit_id',
        'nights',
        'total_minor',
        'currency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nights' => 'array',
            'total_minor' => 'integer',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }
}
