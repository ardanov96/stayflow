<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Actions;

use App\Modules\Catalog\Contracts\CatalogService;
use App\Modules\Reservations\Application\Support\ResolvesReservationAggregate;
use App\Modules\Reservations\Contracts\DTOs\ReservationDTO;
use App\Modules\Reservations\Contracts\ReservationRepository;
use App\Modules\Reservations\Domain\Exceptions\ReservationNotFoundException;
use App\Modules\Reservations\Domain\Exceptions\RoomUnitNotAvailableException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Assigns a physical room to a reservation.
 *
 * The **database** refuses a room that is already booked for one of the
 * nights: the unique index on (room unit, night) enforces the invariant 7
 * of docs/DOMAIN_MODEL.md section 3. Two concurrent assignments of the
 * same room cannot both succeed, whichever one gets there first.
 */
final readonly class AssignRoomUnitAction
{
    use ResolvesReservationAggregate;

    public function __construct(
        private CatalogService $catalogService,
        private ReservationRepository $reservations,
    ) {}

    /**
     * Assign a physical room to one of the rooms of the reservation.
     */
    public function assign(
        int $organizationId,
        int $reservationId,
        int $roomTypeId,
        int $roomUnitId,
        ?int $actorId = null,
    ): ReservationDTO {
        $stored = $this->reservations->find($organizationId, $reservationId);

        if ($stored === null) {
            throw ReservationNotFoundException::forId($reservationId);
        }

        $room = $this->roomOf($stored, $roomTypeId);
        $dates = $this->datesOf($room);
        $unit = $this->unitOf($roomTypeId, $roomUnitId);

        return DB::transaction(function () use (
            $organizationId,
            $reservationId,
            $stored,
            $room,
            $roomTypeId,
            $roomUnitId,
            $dates,
            $unit,
            $actorId,
        ): ReservationDTO {
            try {
                $this->reservations->bookNights(
                    organizationId: $organizationId,
                    reservationId: $reservationId,
                    reservationRoomId: (int) $room['id'],
                    roomUnitId: $roomUnitId,
                    dates: $dates,
                );
            } catch (UniqueConstraintViolationException) {
                throw RoomUnitNotAvailableException::booked($roomUnitId);
            }

            $reservation = $this->aggregate($stored);
            $reservation->assignUnit($roomTypeId, $roomUnitId);

            $this->reservations->save($reservation);
            $this->reservations->saveRooms($reservation);

            foreach ($reservation->pullEvents() as $event) {
                $this->reservations->appendEvent(
                    organizationId: $organizationId,
                    reservationId: $reservationId,
                    type: 'assigned',
                    payload: [
                        'room_type_id' => $roomTypeId,
                        'room_unit_id' => $roomUnitId,
                        'room_number' => $unit->number,
                    ],
                    actorId: $actorId,
                );

                event($event);
            }

            return $this->reservations->find($organizationId, $reservationId)
                ?? throw ReservationNotFoundException::forId($reservationId);
        });
    }

    /**
     * Take the physical room away: the nights go back to the pool.
     */
    public function unassign(
        int $organizationId,
        int $reservationId,
        int $roomTypeId,
        ?int $actorId = null,
    ): ReservationDTO {
        $stored = $this->reservations->find($organizationId, $reservationId);

        if ($stored === null) {
            throw ReservationNotFoundException::forId($reservationId);
        }

        $room = $this->roomOf($stored, $roomTypeId);

        return DB::transaction(function () use (
            $organizationId,
            $reservationId,
            $stored,
            $room,
            $roomTypeId,
            $actorId,
        ): ReservationDTO {
            $this->reservations->releaseNights($organizationId, (int) $room['id']);

            $reservation = $this->aggregate($stored);
            $reservation->unassignUnit($roomTypeId);

            $this->reservations->save($reservation);
            $this->reservations->saveRooms($reservation);

            foreach ($reservation->pullEvents() as $event) {
                $this->reservations->appendEvent(
                    organizationId: $organizationId,
                    reservationId: $reservationId,
                    type: 'unassigned',
                    payload: ['room_type_id' => $roomTypeId],
                    actorId: $actorId,
                );

                event($event);
            }

            return $this->reservations->find($organizationId, $reservationId)
                ?? throw ReservationNotFoundException::forId($reservationId);
        });
    }

    /**
     * Move the reservation to another physical room of the same type.
     *
     * The nights of the old room go back and the nights of the new room
     * are taken, in one transaction: the guest is never without a room.
     */
    public function move(
        int $organizationId,
        int $reservationId,
        int $roomTypeId,
        int $newRoomUnitId,
        ?int $actorId = null,
    ): ReservationDTO {
        $stored = $this->reservations->find($organizationId, $reservationId);

        if ($stored === null) {
            throw ReservationNotFoundException::forId($reservationId);
        }

        $room = $this->roomOf($stored, $roomTypeId);
        $dates = $this->datesOf($room);
        $unit = $this->unitOf($roomTypeId, $newRoomUnitId);

        return DB::transaction(function () use (
            $organizationId,
            $reservationId,
            $stored,
            $room,
            $roomTypeId,
            $newRoomUnitId,
            $dates,
            $unit,
            $actorId,
        ): ReservationDTO {
            $this->reservations->releaseNights($organizationId, (int) $room['id']);

            try {
                $this->reservations->bookNights(
                    organizationId: $organizationId,
                    reservationId: $reservationId,
                    reservationRoomId: (int) $room['id'],
                    roomUnitId: $newRoomUnitId,
                    dates: $dates,
                );
            } catch (UniqueConstraintViolationException) {
                throw RoomUnitNotAvailableException::booked($newRoomUnitId);
            }

            $reservation = $this->aggregate($stored);
            $reservation->assignUnit($roomTypeId, $newRoomUnitId);

            $this->reservations->save($reservation);
            $this->reservations->saveRooms($reservation);

            foreach ($reservation->pullEvents() as $event) {
                $this->reservations->appendEvent(
                    organizationId: $organizationId,
                    reservationId: $reservationId,
                    type: 'moved',
                    payload: [
                        'room_type_id' => $roomTypeId,
                        'room_unit_id' => $newRoomUnitId,
                        'room_number' => $unit->number,
                    ],
                    actorId: $actorId,
                );

                event($event);
            }

            return $this->reservations->find($organizationId, $reservationId)
                ?? throw ReservationNotFoundException::forId($reservationId);
        });
    }

    /**
     * The room of the reservation, of the given room type.
     *
     * @return array<string, mixed>
     */
    private function roomOf(ReservationDTO $stored, int $roomTypeId): array
    {
        foreach ($stored->rooms as $room) {
            if ((int) $room['room_type_id'] === $roomTypeId) {
                return $room;
            }
        }

        throw new InvalidArgumentException(
            "The reservation has no room of the type {$roomTypeId}."
        );
    }

    /**
     * The nights of a room of the reservation.
     *
     * @param  array<string, mixed>  $room
     * @return list<string>
     */
    private function datesOf(array $room): array
    {
        $dates = [];

        foreach ((array) $room['nights'] as $night) {
            $dates[] = (string) $night['date'];
        }

        return $dates;
    }

    /**
     * The physical room, when it can take the reservation.
     */
    private function unitOf(int $roomTypeId, int $roomUnitId): object
    {
        foreach ($this->catalogService->getRoomUnitsForType($roomTypeId) as $unit) {
            if ($unit->id !== $roomUnitId) {
                continue;
            }

            if (! $unit->isActive) {
                throw RoomUnitNotAvailableException::inactive($roomUnitId);
            }

            return $unit;
        }

        throw RoomUnitNotAvailableException::unknown($roomUnitId);
    }
}
