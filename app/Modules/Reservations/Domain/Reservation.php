<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain;

use App\Modules\Reservations\Domain\Events\ReservationCreated;
use App\Modules\Reservations\Domain\Events\ReservationModified;
use App\Modules\Reservations\Domain\Events\ReservationStatusChanged;
use App\Modules\Reservations\Domain\Events\RoomAssigned;
use App\Modules\Reservations\Domain\Exceptions\InvalidReservationTransition;
use App\Support\Date\StayDate;
use InvalidArgumentException;

/**
 * The reservation aggregate.
 *
 * It owns the state machine of a reservation
 * (docs/ARCHITECTURE.md §7): a status only changes along the transitions
 * of {@see ReservationStatus}, and every change is a **versioned event**
 * rather than a new state.
 *
 * Pure domain: no persistence, no framework. The events are recorded
 * here and published by the application layer, after the transaction
 * committed.
 */
final class Reservation
{
    /**
     * @var list<ReservationRoom>
     */
    private array $rooms = [];

    /**
     * @var list<object>
     */
    private array $events = [];

    private ?int $id = null;

    /**
     * The version the row had when the reservation was read.
     *
     * It is what the write is guarded with: a caller that read the
     * reservation before a change does not write over it.
     */
    private int $expectedVersion = 0;

    /**
     * The inventory hold this reservation came from.
     */
    private ?int $holdId = null;

    /**
     * @param  list<ReservationRoom>  $rooms
     */
    public function __construct(
        public readonly int $organizationId,
        public readonly int $propertyId,
        public readonly string $code,
        public readonly ReservationSource $source,
        public readonly ?string $channelRef,
        public int $guestId,
        public string $arrival,
        public string $departure,
        public int $adults,
        public int $children,
        public int $totalMinor,
        public string $currency,
        private ReservationStatus $status = ReservationStatus::PendingPayment,
        array $rooms = [],
        private int $version = 1,
    ) {
        $this->assertValid();

        foreach ($rooms as $room) {
            $this->rooms[] = $room;
        }
    }

    /**
     * A reservation created through the booking engine: the guest has
     * not paid yet.
     *
     * @param  list<ReservationRoom>  $rooms
     */
    public static function pendingPayment(
        int $organizationId,
        int $propertyId,
        string $code,
        ReservationSource $source,
        ?string $channelRef,
        int $guestId,
        string $arrival,
        string $departure,
        int $adults,
        int $children,
        int $totalMinor,
        string $currency,
        array $rooms = [],
    ): self {
        return new self(
            organizationId: $organizationId,
            propertyId: $propertyId,
            code: $code,
            source: $source,
            channelRef: $channelRef,
            guestId: $guestId,
            arrival: $arrival,
            departure: $departure,
            adults: $adults,
            children: $children,
            totalMinor: $totalMinor,
            currency: $currency,
            status: ReservationStatus::PendingPayment,
            rooms: $rooms,
        );
    }

    /**
     * A reservation created by the staff of the hotel or by an OTA: it is
     * confirmed right away.
     *
     * @param  list<ReservationRoom>  $rooms
     */
    public static function confirmed(
        int $organizationId,
        int $propertyId,
        string $code,
        ReservationSource $source,
        ?string $channelRef,
        int $guestId,
        string $arrival,
        string $departure,
        int $adults,
        int $children,
        int $totalMinor,
        string $currency,
        array $rooms = [],
    ): self {
        return new self(
            organizationId: $organizationId,
            propertyId: $propertyId,
            code: $code,
            source: $source,
            channelRef: $channelRef,
            guestId: $guestId,
            arrival: $arrival,
            departure: $departure,
            adults: $adults,
            children: $children,
            totalMinor: $totalMinor,
            currency: $currency,
            status: ReservationStatus::Confirmed,
            rooms: $rooms,
        );
    }

    /**
     * A reservation that already exists, read from the database.
     *
     * @param  list<ReservationRoom>  $rooms
     */
    public static function restore(
        int $id,
        int $organizationId,
        int $propertyId,
        string $code,
        ReservationSource $source,
        ?string $channelRef,
        int $guestId,
        string $arrival,
        string $departure,
        int $adults,
        int $children,
        int $totalMinor,
        string $currency,
        ReservationStatus $status,
        array $rooms = [],
        int $version = 1,
        ?int $holdId = null,
    ): self {
        $reservation = new self(
            organizationId: $organizationId,
            propertyId: $propertyId,
            code: $code,
            source: $source,
            channelRef: $channelRef,
            guestId: $guestId,
            arrival: $arrival,
            departure: $departure,
            adults: $adults,
            children: $children,
            totalMinor: $totalMinor,
            currency: $currency,
            status: $status,
            rooms: $rooms,
            version: $version,
        );

        $reservation->id = $id;
        $reservation->expectedVersion = $version;
        $reservation->holdId = $holdId;

        return $reservation;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    /**
     * The identifier the database gave to the reservation.
     *
     * The reservation is created before it is written, so the identifier
     * is known only after the insert. This is where the creation is
     * recorded.
     */
    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            return;
        }

        $this->id = $id;

        $this->record(new ReservationCreated(
            organizationId: $this->organizationId,
            propertyId: $this->propertyId,
            reservationId: $id,
            code: $this->code,
            status: $this->status->value,
            source: $this->source->value,
        ));
    }

    public function status(): ReservationStatus
    {
        return $this->status;
    }

    /**
     * The number of the change: it is what the callers use to make sure
     * they do not write over a change they did not read.
     */
    public function version(): int
    {
        return $this->version;
    }

    /**
     * The version the row had when the reservation was read.
     */
    public function expectedVersion(): int
    {
        return $this->expectedVersion;
    }

    /**
     * The inventory hold this reservation came from.
     */
    public function holdId(): ?int
    {
        return $this->holdId;
    }

    /**
     * Link the reservation to the inventory hold it came from.
     */
    public function attachHold(int $holdId): void
    {
        $this->holdId = $holdId;
    }

    /**
     * @return list<ReservationRoom>
     */
    public function rooms(): array
    {
        return $this->rooms;
    }

    /**
     * The number of nights of the reservation.
     */
    public function nightsCount(): int
    {
        $nights = 0;

        foreach ($this->rooms as $room) {
            $nights += $room->nightsCount();
        }

        return $nights;
    }

    /**
     * The events the reservation recorded, oldest first.
     *
     * @return list<object>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * The events, and the list is emptied: the caller publishes them.
     *
     * @return list<object>
     */
    public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    /**
     * The payment came: the reservation is confirmed.
     */
    public function confirm(?string $reason = null): void
    {
        $this->transitionTo(ReservationStatus::Confirmed, $reason, 'confirmed');
    }

    /**
     * The guest or the staff cancelled the reservation.
     */
    public function cancel(?string $reason = null): void
    {
        $this->transitionTo(ReservationStatus::Cancelled, $reason, 'cancelled');
    }

    /**
     * The hold expired before the payment came.
     */
    public function expire(?string $reason = null): void
    {
        $this->transitionTo(ReservationStatus::Expired, $reason, 'expired');
    }

    /**
     * The guest arrived.
     */
    public function checkIn(): void
    {
        $this->transitionTo(ReservationStatus::CheckedIn, null, 'checked in');
    }

    /**
     * The guest left.
     */
    public function checkOut(): void
    {
        $this->transitionTo(ReservationStatus::CheckedOut, null, 'checked out');
    }

    /**
     * The guest did not arrive.
     */
    public function markNoShow(): void
    {
        $this->transitionTo(ReservationStatus::NoShow, null, 'marked as no show');
    }

    /**
     * Move the reservation to another state, when the machine allows it.
     */
    public function transitionTo(ReservationStatus $to, ?string $reason = null, string $action = 'moved'): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw InvalidReservationTransition::from($this->status, $to, $action);
        }

        $from = $this->status;
        $this->status = $to;
        $this->version++;

        $this->record(new ReservationStatusChanged(
            organizationId: $this->organizationId,
            propertyId: $this->propertyId,
            reservationId: $this->id ?? 0,
            from: $from,
            to: $to,
            reason: $reason,
            version: $this->version,
        ));
    }

    /**
     * A modification of the reservation: the dates, the guests, the
     * number of people.
     *
     * A modification is not a state: it is a versioned event
     * (docs/ARCHITECTURE.md §7). The version is bumped, so a caller that
     * read the reservation before the change does not write over it.
     *
     * @param  array<string, mixed>  $changes
     */
    public function modify(array $changes): void
    {
        if ($changes === []) {
            return;
        }

        if ($this->status->isTerminal()) {
            throw InvalidReservationTransition::notModifiable($this->status);
        }

        $this->version++;

        $this->record(new ReservationModified(
            organizationId: $this->organizationId,
            propertyId: $this->propertyId,
            reservationId: $this->id ?? 0,
            changes: $changes,
            version: $this->version,
        ));
    }

    /**
     * Move the stay to other nights.
     */
    public function changeStay(string $arrival, string $departure): void
    {
        $this->assertValidStay($arrival, $departure);

        $previous = ['arrival' => $this->arrival, 'departure' => $this->departure];

        $this->arrival = $arrival;
        $this->departure = $departure;

        $this->recordChange([
            'arrival' => ['from' => $previous['arrival'], 'to' => $arrival],
            'departure' => ['from' => $previous['departure'], 'to' => $departure],
        ]);
    }

    /**
     * Change the number of people.
     */
    public function changeOccupancy(int $adults, int $children): void
    {
        if ($adults < 1) {
            throw new InvalidArgumentException('A reservation needs at least one adult.');
        }

        if ($children < 0) {
            throw new InvalidArgumentException('The number of children cannot be negative.');
        }

        $previous = ['adults' => $this->adults, 'children' => $this->children];

        $this->adults = $adults;
        $this->children = $children;

        $this->recordChange([
            'adults' => ['from' => $previous['adults'], 'to' => $adults],
            'children' => ['from' => $previous['children'], 'to' => $children],
        ]);
    }

    /**
     * Change the guests of the reservation.
     */
    public function changeGuest(int $guestId): void
    {
        if ($guestId < 1) {
            throw new InvalidArgumentException('A reservation needs a primary guest.');
        }

        $previous = $this->guestId;

        $this->guestId = $guestId;

        $this->recordChange(['guest_id' => ['from' => $previous, 'to' => $guestId]]);
    }

    /**
     * Change the total of the reservation and the currency it is in.
     */
    public function changeTotal(int $totalMinor, string $currency): void
    {
        if ($totalMinor < 0) {
            throw new InvalidArgumentException('The total of a reservation cannot be negative.');
        }

        if (trim($currency) === '') {
            throw new InvalidArgumentException('A reservation needs a currency.');
        }

        $previous = $this->totalMinor;

        $this->totalMinor = $totalMinor;
        $this->currency = $currency;

        $this->recordChange([
            'total_minor' => ['from' => $previous, 'to' => $totalMinor],
        ]);
    }

    /**
     * Replace the rooms of the reservation.
     *
     * @param  list<ReservationRoom>  $rooms
     */
    public function changeRooms(array $rooms): void
    {
        $this->rooms = $rooms;

        $this->recordChange(['rooms' => ['count' => count($this->rooms)]]);
    }

    /**
     * Assign a physical room to one of the rooms of the reservation.
     */
    public function assignUnit(int $roomTypeId, int $roomUnitId): void
    {
        if ($this->status->isTerminal()) {
            throw InvalidReservationTransition::notModifiable($this->status);
        }

        $rooms = [];
        $assigned = false;

        foreach ($this->rooms as $room) {
            if (! $assigned && $room->roomTypeId === $roomTypeId) {
                $rooms[] = $room->withUnit($roomUnitId);
                $assigned = true;

                continue;
            }

            $rooms[] = $room;
        }

        if (! $assigned) {
            throw new InvalidArgumentException("The reservation has no room of type {$roomTypeId}.");
        }

        $this->rooms = $rooms;
        $this->version++;

        $this->record(new RoomAssigned(
            organizationId: $this->organizationId,
            propertyId: $this->propertyId,
            reservationId: $this->id ?? 0,
            roomTypeId: $roomTypeId,
            roomUnitId: $roomUnitId,
            nights: [],
        ));
    }

    private function record(object $event): void
    {
        $this->events[] = $event;
    }

    /**
     * Take the physical room away: the nights go back to the pool.
     */
    public function unassignUnit(int $roomTypeId): void
    {
        if ($this->status->isTerminal()) {
            throw InvalidReservationTransition::notModifiable($this->status);
        }

        $rooms = [];
        $found = false;

        foreach ($this->rooms as $room) {
            if (! $found && $room->roomTypeId === $roomTypeId) {
                $rooms[] = new ReservationRoom(
                    roomTypeId: $room->roomTypeId,
                    ratePlanId: $room->ratePlanId,
                    roomUnitId: null,
                    nights: $room->nights,
                    id: $room->id,
                );
                $found = true;

                continue;
            }

            $rooms[] = $room;
        }

        if (! $found) {
            throw new InvalidArgumentException(
                "The reservation has no room of the type {$roomTypeId}."
            );
        }

        $this->rooms = $rooms;
        $this->version++;

        $this->record(new RoomAssigned(
            organizationId: $this->organizationId,
            propertyId: $this->propertyId,
            reservationId: $this->id ?? 0,
            roomTypeId: $roomTypeId,
            roomUnitId: null,
            nights: [],
        ));
    }

    /**
     * Record a change: the version is bumped and the event says what
     * changed.
     *
     * @param  array<string, mixed>  $changes
     */
    private function recordChange(array $changes): void
    {
        $this->version++;

        $this->record(new ReservationModified(
            organizationId: $this->organizationId,
            propertyId: $this->propertyId,
            reservationId: $this->id ?? 0,
            changes: $changes,
            version: $this->version,
        ));
    }

    private function assertValidStay(string $arrival, string $departure): void
    {
        if (! StayDate::fromString($arrival)->isBefore(StayDate::fromString($departure))) {
            throw new InvalidArgumentException(
                "The departure date ({$departure}) must be after the arrival date ({$arrival})."
            );
        }
    }

    private function assertValid(): void
    {
        if ($this->organizationId < 1 || $this->propertyId < 1) {
            throw new InvalidArgumentException('A reservation belongs to an organization and to a property.');
        }

        if (trim($this->code) === '') {
            throw new InvalidArgumentException('A reservation needs a code.');
        }

        if ($this->guestId < 1) {
            throw new InvalidArgumentException('A reservation needs a primary guest.');
        }

        if ($this->adults < 1) {
            throw new InvalidArgumentException('A reservation needs at least one adult.');
        }

        if ($this->children < 0) {
            throw new InvalidArgumentException('The number of children cannot be negative.');
        }

        if ($this->totalMinor < 0) {
            throw new InvalidArgumentException('The total of a reservation cannot be negative.');
        }

        if (trim($this->currency) === '') {
            throw new InvalidArgumentException('A reservation needs a currency.');
        }

        if (! $this->checkInDate()->isBefore($this->checkOutDate())) {
            throw new InvalidArgumentException(
                "The departure date ({$this->departure}) must be after the arrival date ({$this->arrival})."
            );
        }
    }

    private function checkInDate(): StayDate
    {
        return StayDate::fromString($this->arrival);
    }

    private function checkOutDate(): StayDate
    {
        return StayDate::fromString($this->departure);
    }
}
