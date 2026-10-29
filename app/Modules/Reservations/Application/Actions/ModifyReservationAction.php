<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Actions;

use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use App\Modules\Reservations\Contracts\DTOs\ReservationDTO;
use App\Modules\Reservations\Contracts\DTOs\ReservationModificationDTO;
use App\Modules\Reservations\Contracts\ReservationRepository;
use App\Modules\Reservations\Domain\Events\ReservationModified;
use App\Modules\Reservations\Domain\Exceptions\ReservationNotAvailableException;
use App\Modules\Reservations\Domain\Exceptions\ReservationNotFoundException;
use App\Modules\Reservations\Domain\Reservation;
use App\Modules\Reservations\Domain\ReservationRoom;
use App\Modules\Reservations\Domain\ReservationSource;
use App\Modules\Reservations\Domain\ReservationStatus;
use App\Support\Date\StayDate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Modifies a reservation.
 *
 * The dates, the room type, the rate plan, the occupancy and the guests
 * can change. The **inventory follows atomically**: the nights the
 * reservation no longer covers go back to availability, the new nights are
 * taken, and the price is recalculated. It all happens in one
 * transaction, so a modification that cannot be satisfied leaves the
 * reservation exactly as it was.
 */
final readonly class ModifyReservationAction
{
    public function __construct(
        private InventoryService $inventoryService,
        private ReservationRepository $reservations,
        private Auditor $auditor,
    ) {}

    /**
     * @param  list<int>  $companionGuestIds
     */
    public function execute(
        int $organizationId,
        int $reservationId,
        ?string $arrival = null,
        ?string $departure = null,
        ?int $roomTypeId = null,
        ?int $ratePlanId = null,
        ?int $adults = null,
        ?int $children = null,
        ?int $guestId = null,
        array $companionGuestIds = [],
        ?int $actorId = null,
    ): ReservationModificationDTO {
        $stored = $this->reservations->find($organizationId, $reservationId);

        if ($stored === null) {
            throw ReservationNotFoundException::forId($reservationId);
        }

        $reservation = $this->toAggregate($stored);

        $oldRooms = $reservation->rooms();
        $oldRoomTypeId = $oldRooms[0]->roomTypeId ?? 0;
        $oldRatePlanId = $oldRooms[0]->ratePlanId ?? null;
        $oldNights = $this->nightsOf($oldRooms);
        $units = max(1, count($oldRooms));

        $newArrival = $arrival ?? $reservation->arrival;
        $newDeparture = $departure ?? $reservation->departure;
        $newRoomTypeId = $roomTypeId ?? $oldRoomTypeId;
        $newRatePlanId = $ratePlanId ?? $oldRatePlanId;
        $newAdults = $adults ?? $reservation->adults;
        $newChildren = $children ?? $reservation->children;

        // 1. The quote of the new stay decides the availability and the
        // price. When it says no, nothing is touched.
        $quote = $this->inventoryService->quote(
            propertyId: $reservation->propertyId,
            roomTypeIds: [$newRoomTypeId],
            checkInDate: $newArrival,
            checkOutDate: $newDeparture,
            adults: $newAdults,
            children: $newChildren,
            ratePlanId: $newRatePlanId,
        );

        $roomTypeQuote = null;

        foreach ($quote->roomTypes as $candidate) {
            if ($candidate->roomTypeId === $newRoomTypeId) {
                $roomTypeQuote = $candidate;

                break;
            }
        }

        if ($roomTypeQuote === null) {
            throw new InvalidArgumentException("Room type {$newRoomTypeId} not found.");
        }

        if (! $roomTypeQuote->isAvailable) {
            throw ReservationNotAvailableException::forRoomType($newRoomTypeId, $roomTypeQuote->restrictionViolations);
        }

        $priceQuote = $roomTypeQuote->priceQuote;

        if ($priceQuote === null) {
            throw new InvalidArgumentException("The rate plan {$newRatePlanId} has no price for the room type {$newRoomTypeId}.");
        }

        $nights = [];
        $newNights = [];

        foreach ($priceQuote->nightlyBreakdowns as $breakdown) {
            $nights[] = [
                'date' => $breakdown->date,
                'price_minor' => $breakdown->totalPrice->amountMinor,
            ];

            $newNights[] = $breakdown->date;
        }

        // 2. The delta: the nights that are given back, the nights that
        // are taken.
        $roomTypeChanged = $newRoomTypeId !== $oldRoomTypeId;

        $removed = $roomTypeChanged
            ? $oldNights
            : array_values(array_diff($oldNights, $newNights));

        $added = $roomTypeChanged
            ? $newNights
            : array_values(array_diff($newNights, $oldNights));

        $previousTotal = $reservation->totalMinor;

        // 3. Everything, in one transaction.
        return DB::transaction(function () use (
            $organizationId,
            $reservationId,
            $reservation,
            $oldRoomTypeId,
            $newRoomTypeId,
            $newRatePlanId,
            $newArrival,
            $newDeparture,
            $newAdults,
            $newChildren,
            $guestId,
            $companionGuestIds,
            $actorId,
            $units,
            $removed,
            $added,
            $nights,
            $priceQuote,
            $previousTotal,
        ): ReservationModificationDTO {
            // The nights the reservation no longer covers go back.
            if ($removed !== []) {
                $this->inventoryService->releaseSoldUnits(
                    propertyId: $reservation->propertyId,
                    roomTypeId: $oldRoomTypeId,
                    dates: $removed,
                    units: $units,
                    userId: $actorId,
                );
            }

            // The new nights are taken.
            if ($added !== []) {
                $hold = $this->inventoryService->hold(
                    propertyId: $reservation->propertyId,
                    roomTypeId: $newRoomTypeId,
                    checkIn: $this->firstOf($added),
                    checkOut: $this->dayAfter($this->lastOf($added)),
                    units: $units,
                    idempotencyKey: 'mod-'.$reservationId.'-v'.$reservation->version(),
                    userId: $actorId,
                );

                $this->inventoryService->commitHold($hold->id, $reservationId, $actorId);
            }

            // The reservation itself.
            if ($newArrival !== $reservation->arrival || $newDeparture !== $reservation->departure) {
                $reservation->changeStay($newArrival, $newDeparture);
            }

            if ($newAdults !== $reservation->adults || $newChildren !== $reservation->children) {
                $reservation->changeOccupancy($newAdults, $newChildren);
            }

            if ($guestId !== null && $guestId !== $reservation->guestId) {
                $reservation->changeGuest($guestId);
            }

            $reservation->changeTotal(
                $priceQuote->subtotal->amountMinor,
                $priceQuote->subtotal->currencyCode,
            );

            $reservation->changeRooms([new ReservationRoom(
                roomTypeId: $newRoomTypeId,
                ratePlanId: $newRatePlanId,
                roomUnitId: null,
                nights: $nights,
            )]);

            $this->reservations->save($reservation);
            $this->reservations->saveRooms($reservation);

            if ($guestId !== null) {
                $this->reservations->saveGuests($reservation, $guestId, $companionGuestIds);
            }

            // The trail and the events.
            $changes = [];

            foreach ($reservation->pullEvents() as $event) {
                $changes = $event instanceof ReservationModified
                    ? $event->changes
                    : $changes;

                $this->reservations->appendEvent(
                    organizationId: $organizationId,
                    reservationId: $reservationId,
                    type: 'modified',
                    payload: ['version' => $reservation->version()],
                    actorId: $actorId,
                );

                event($event);
            }

            $this->auditor->recordCustom(
                'reservation.modified',
                new SubjectChange(
                    subjectId: $reservationId,
                    subjectType: 'Reservation',
                    oldValues: ['total_minor' => $previousTotal],
                    newValues: [
                        'total_minor' => $reservation->totalMinor,
                        'arrival' => $reservation->arrival,
                        'departure' => $reservation->departure,
                    ],
                    metadata: ['actor_id' => $actorId],
                ),
                new TenantHint(
                    organizationId: $organizationId,
                    propertyId: $reservation->propertyId,
                ),
            );

            $updated = $this->reservations->find($organizationId, $reservationId);

            if ($updated === null) {
                throw ReservationNotFoundException::forId($reservationId);
            }

            return new ReservationModificationDTO(
                reservation: $updated,
                changes: $changes,
                nightsAdded: $added,
                nightsRemoved: $removed,
                previousTotalMinor: $previousTotal,
                totalMinor: $reservation->totalMinor,
                currency: $reservation->currency,
            );
        });
    }

    private function toAggregate(ReservationDTO $stored): Reservation
    {
        $rooms = [];

        foreach ($stored->rooms as $room) {
            $rooms[] = new ReservationRoom(
                roomTypeId: (int) $room['room_type_id'],
                ratePlanId: $room['rate_plan_id'] !== null ? (int) $room['rate_plan_id'] : null,
                roomUnitId: $room['room_unit_id'] !== null ? (int) $room['room_unit_id'] : null,
                nights: (array) $room['nights'],
            );
        }

        return Reservation::restore(
            id: $stored->id,
            organizationId: $stored->organizationId,
            propertyId: $stored->propertyId,
            code: $stored->code,
            source: ReservationSource::fromString($stored->source),
            channelRef: $stored->channelRef,
            guestId: $stored->guestId,
            arrival: $stored->arrival,
            departure: $stored->departure,
            adults: $stored->adults,
            children: $stored->children,
            totalMinor: $stored->totalMinor,
            currency: $stored->currency,
            status: ReservationStatus::from($stored->status),
            rooms: $rooms,
            version: $stored->version,
            holdId: $stored->holdId,
        );
    }

    /**
     * @param  list<ReservationRoom>  $rooms
     * @return list<string>
     */
    private function nightsOf(array $rooms): array
    {
        $nights = [];

        foreach ($rooms as $room) {
            foreach ($room->nights as $night) {
                $nights[] = (string) $night['date'];
            }
        }

        return array_values(array_unique($nights));
    }

    /**
     * @param  list<string>  $dates
     */
    private function firstOf(array $dates): string
    {
        return (string) min($dates);
    }

    /**
     * @param  list<string>  $dates
     */
    private function lastOf(array $dates): string
    {
        return (string) max($dates);
    }

    private function dayAfter(string $date): string
    {
        return StayDate::fromString($date)->plusDays(1)->toString();
    }
}
