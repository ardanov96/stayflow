<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Infrastructure\Models;

use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A guest of a reservation: the primary one, or a companion.
 *
 * @property int $id
 * @property int $reservation_id
 * @property int $guest_id
 * @property string $role
 */
class ReservationGuest extends Model
{
    use BelongsToTenant;

    protected $table = 'reservation_guests';

    protected $fillable = [
        'organization_id',
        'reservation_id',
        'guest_id',
        'role',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }
}
