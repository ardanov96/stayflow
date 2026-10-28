<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Infrastructure\Models;

use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of the trail of a reservation.
 *
 * The trail is append-only: it says what happened to the reservation and
 * when, and it is never rewritten.
 *
 * @property int $id
 * @property int $reservation_id
 * @property string $type
 * @property array<string, mixed>|null $payload
 * @property int|null $actor_id
 */
class ReservationEvent extends Model
{
    use BelongsToTenant;

    protected $table = 'reservation_events';

    protected $fillable = [
        'organization_id',
        'reservation_id',
        'type',
        'payload',
        'actor_id',
        'at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }
}
