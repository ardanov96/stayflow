<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Infrastructure\Models;

use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reservation of the PMS.
 *
 * The state of the reservation is owned by the domain
 * (`App\Modules\Reservations\Domain\Reservation`); the model only
 * carries it.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $property_id
 * @property string $code
 * @property string $status
 * @property string $source
 * @property string|null $channel_ref
 * @property int $guest_id
 * @property string $arrival
 * @property string $departure
 * @property int $adults
 * @property int $children
 * @property int $total_minor
 * @property string $currency
 * @property int $version
 * @property int|null $hold_id
 */
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    use BelongsToTenant;

    protected $table = 'reservations';

    protected $fillable = [
        'organization_id',
        'property_id',
        'code',
        'status',
        'source',
        'channel_ref',
        'guest_id',
        'arrival',
        'departure',
        'adults',
        'children',
        'total_minor',
        'currency',
        'version',
        'hold_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'arrival' => 'date',
            'departure' => 'date',
            'adults' => 'integer',
            'children' => 'integer',
            'total_minor' => 'integer',
            'version' => 'integer',
        ];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(ReservationRoom::class, 'reservation_id');
    }

    public function guests(): HasMany
    {
        return $this->hasMany(ReservationGuest::class, 'reservation_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReservationEvent::class, 'reservation_id');
    }
}
