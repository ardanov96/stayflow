<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Actions;

use App\Modules\Catalog\Contracts\CatalogService;
use App\Modules\Catalog\Contracts\DTOs\RoomUnitDTO;
use App\Modules\Reservations\Contracts\ReservationRepository;
use App\Modules\Reservations\Domain\Exceptions\ReservationNotFoundException;
use InvalidArgumentException;

/**
 * Suggests the physical rooms that are free for the nights of a
 * reservation.
 *
 * A room is free when it is not booked for **any** of the nights of the
 * stay: a room that is free on the first night and taken on the second
 * is not free.
 */
final readonly class SuggestRoomUnitsAction
{
    public function __construct(
        private CatalogService $catalogService,
        private ReservationRepository $reservations,
    ) {}

    /**
     * @return list<RoomUnitDTO>
     */
    public function suggest(int $organizationId, int $reservationId, int $roomTypeId): array
    {
        $stored = $this->reservations->find($organizationId, $reservationId);

        if ($stored === null) {
            throw ReservationNotFoundException::forId($reservationId);
        }

        $dates = [];

        foreach ($stored->rooms as $room) {
            if ((int) $room['room_type_id'] !== $roomTypeId) {
                continue;
            }

            foreach ((array) $room['nights'] as $night) {
                $dates[] = (string) $night['date'];
            }
        }

        if ($dates === []) {
            throw new InvalidArgumentException(
                "The reservation has no room of the type {$roomTypeId}."
            );
        }

        $free = [];

        foreach ($this->catalogService->getRoomUnitsForType($roomTypeId) as $unit) {
            if (! $unit->isActive) {
                continue;
            }

            $booked = $this->reservations->bookedNightsOf($organizationId, $unit->id);

            if (array_intersect($dates, $booked) !== []) {
                continue;
            }

            $free[] = $unit;
        }

        return $free;
    }
}
