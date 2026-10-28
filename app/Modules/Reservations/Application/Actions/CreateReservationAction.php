<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Actions;

use App\Modules\Catalog\Contracts\CatalogService;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use App\Modules\Reservations\Contracts\DTOs\ReservationDTO;
use App\Modules\Reservations\Contracts\ReservationRepository;
use App\Modules\Reservations\Domain\Exceptions\ReservationNotAvailableException;
use App\Modules\Reservations\Domain\Reservation;
use App\Modules\Reservations\Domain\ReservationRoom;
use App\Modules\Reservations\Domain\ReservationSource;
use App\Support\Date\PropertyClock;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates a reservation for the staff of the hotel.
 *
 * The whole thing happens in **one transaction**: the inventory is held,
 * the reservation is written, and the hold is committed (the units become
 * sold). Either the guest has a room and the inventory says so, or nothing
 * happened at all.
 *
 * The request is idempotent: the same idempotency key gives the same hold,
 * and the same hold gives the same reservation. A double submit therefore
 * creates **one** reservation.
 */
final readonly class CreateReservationAction
{
    public function __construct(
        private InventoryService $inventoryService,
        private CatalogService $catalogService,
        private ReservationRepository $reservations,
        private Auditor $auditor,
    ) {}

    /**
     * @param  list<int>  $companionGuestIds
     */
    public function execute(
        int $organizationId,
        int $propertyId,
        int $roomTypeId,
        int $ratePlanId,
        string $arrival,
        string $departure,
        int $adults,
        int $children,
        int $guestId,
        array $companionGuestIds = [],
        string $idempotencyKey = '',
        ?int $actorId = null,
    ): ReservationDTO {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException(
                'The idempotency key is required: a double submit must not create two reservations.'
            );
        }

        $property = $this->catalogService->getProperty($propertyId);

        if ($property === null || $property->organizationId !== $organizationId) {
            throw new InvalidArgumentException("Property {$propertyId} not found.");
        }

        // The quote: the availability of the nights and the price of the
        // stay. It is the same quote the staff sees before they press the
        // button.
        $quote = $this->inventoryService->quote(
            propertyId: $propertyId,
            roomTypeIds: [$roomTypeId],
            checkInDate: $arrival,
            checkOutDate: $departure,
            adults: $adults,
            children: $children,
            ratePlanId: $ratePlanId,
        );

        $roomTypeQuote = null;

        foreach ($quote->roomTypes as $candidate) {
            if ($candidate->roomTypeId === $roomTypeId) {
                $roomTypeQuote = $candidate;

                break;
            }
        }

        if ($roomTypeQuote === null) {
            throw new InvalidArgumentException("Room type {$roomTypeId} not found.");
        }

        if (! $roomTypeQuote->isAvailable) {
            throw ReservationNotAvailableException::forRoomType($roomTypeId, $roomTypeQuote->restrictionViolations);
        }

        $priceQuote = $roomTypeQuote->priceQuote;

        if ($priceQuote === null) {
            throw new InvalidArgumentException("The rate plan {$ratePlanId} has no price for the room type {$roomTypeId}.");
        }

        $nights = [];

        foreach ($priceQuote->nightlyBreakdowns as $breakdown) {
            $nights[] = [
                'date' => $breakdown->date,
                'price_minor' => $breakdown->totalPrice->amountMinor,
            ];
        }

        return DB::transaction(function () use (
            $organizationId,
            $propertyId,
            $roomTypeId,
            $ratePlanId,
            $arrival,
            $departure,
            $adults,
            $children,
            $guestId,
            $companionGuestIds,
            $idempotencyKey,
            $actorId,
            $property,
            $nights,
            $priceQuote,
        ): ReservationDTO {
            // The hold. It is idempotent: the same key gives the same
            // hold, so a double submit does not take the room twice.
            $hold = $this->inventoryService->hold(
                propertyId: $propertyId,
                roomTypeId: $roomTypeId,
                checkIn: $arrival,
                checkOut: $departure,
                units: 1,
                idempotencyKey: $idempotencyKey,
                ttlSeconds: null,
                userId: $actorId,
            );

            // The double submit: the hold is the same one, so the
            // reservation is the same one.
            $existing = $this->reservations->findByHold($organizationId, $hold->id);

            if ($existing !== null) {
                return $existing;
            }

            $prefix = 'RSV-'.str_replace('-', '', (new PropertyClock)->today($property->timezone)->toString()).'-';

            $reservation = Reservation::confirmed(
                organizationId: $organizationId,
                propertyId: $propertyId,
                code: $this->reservations->nextCode($organizationId, $propertyId, $prefix),
                source: ReservationSource::pms(),
                channelRef: null,
                guestId: $guestId,
                arrival: $arrival,
                departure: $departure,
                adults: $adults,
                children: $children,
                totalMinor: $priceQuote->subtotal->amountMinor,
                currency: $priceQuote->subtotal->currencyCode,
                rooms: [new ReservationRoom(
                    roomTypeId: $roomTypeId,
                    ratePlanId: $ratePlanId,
                    roomUnitId: null,
                    nights: $nights,
                )],
            );

            $reservation->attachHold($hold->id);

            $this->reservations->save($reservation);
            $this->reservations->saveRooms($reservation);
            $this->reservations->saveGuests($reservation, $guestId, $companionGuestIds);

            // The hold becomes sold: the guest has the room. The hold
            // carries the identifier of the reservation it became.
            $this->inventoryService->commitHold(
                holdId: $hold->id,
                reservationId: (int) $reservation->id(),
                userId: $actorId,
            );

            // The trail of the reservation, and the events of the domain.
            foreach ($reservation->pullEvents() as $event) {
                $this->reservations->appendEvent(
                    organizationId: $organizationId,
                    reservationId: (int) $reservation->id(),
                    type: 'created',
                    payload: ['status' => $reservation->status()->value],
                    actorId: $actorId,
                );

                event($event);
            }

            $this->auditor->recordCustom(
                'reservation.created',
                new SubjectChange(
                    subjectId: (int) $reservation->id(),
                    subjectType: 'Reservation',
                    oldValues: null,
                    newValues: [
                        'code' => $reservation->code,
                        'status' => $reservation->status()->value,
                        'arrival' => $reservation->arrival,
                        'departure' => $reservation->departure,
                        'total_minor' => $reservation->totalMinor,
                        'currency' => $reservation->currency,
                    ],
                    metadata: ['actor_id' => $actorId],
                ),
                new TenantHint(
                    organizationId: $organizationId,
                    propertyId: $propertyId,
                ),
            );

            $stored = $this->reservations->find($organizationId, (int) $reservation->id());

            if ($stored === null) {
                throw new InvalidArgumentException('The reservation could not be read back.');
            }

            return $stored;
        });
    }
}
