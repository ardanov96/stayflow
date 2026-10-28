<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain;

/**
 * The state of a reservation.
 *
 * The state machine is the contract of the module
 * (docs/ARCHITECTURE.md §7, invariant 6 of docs/DOMAIN_MODEL.md §3): a
 * reservation only changes along the transitions below. A change that is
 * not on them is refused, not forced.
 *
 *     pending_payment ──▶ confirmed ──▶ checked_in ──▶ checked_out
 *          │                 │
 *          │                 ├──▶ cancelled
 *          ├──▶ expired      └──▶ no_show
 *          └──▶ cancelled
 */
enum ReservationStatus: string
{
    /** The guest booked and has not paid yet. */
    case PendingPayment = 'pending_payment';

    /** The reservation is confirmed: the guest will arrive. */
    case Confirmed = 'confirmed';

    /** The guest is in the hotel. */
    case CheckedIn = 'checked_in';

    /** The guest left. */
    case CheckedOut = 'checked_out';

    /** The reservation was cancelled. */
    case Cancelled = 'cancelled';

    /** The guest did not arrive. */
    case NoShow = 'no_show';

    /** The hold expired before the payment came. */
    case Expired = 'expired';

    /**
     * The states the reservation can move to from here.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Confirmed, self::Expired, self::Cancelled],
            self::Confirmed => [self::CheckedIn, self::Cancelled, self::NoShow],
            self::CheckedIn => [self::CheckedOut],
            self::CheckedOut, self::Cancelled, self::NoShow, self::Expired => [],
        };
    }

    /**
     * Whether the move to the other state is allowed.
     */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * Whether the reservation is in a state it never leaves.
     */
    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Whether the guest is in the hotel (or was).
     */
    public function isOnSite(): bool
    {
        return $this === self::CheckedIn || $this === self::CheckedOut;
    }
}
