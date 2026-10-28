<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain\Exceptions;

use App\Modules\Reservations\Domain\ReservationStatus;
use RuntimeException;

/**
 * A move that the state machine does not allow.
 */
final class InvalidReservationTransition extends RuntimeException
{
    public static function from(ReservationStatus $from, ReservationStatus $to, string $action): self
    {
        return new self(sprintf(
            'A reservation in state "%s" cannot be %s (the state machine allows: %s).',
            $from->value,
            $action,
            $from->allowedTransitions() === []
                ? 'no further change'
                : implode(', ', array_map(
                    static fn (ReservationStatus $status): string => $status->value,
                    $from->allowedTransitions(),
                )),
        ));
    }

    /**
     * A modification of a reservation that is in a final state.
     */
    public static function notModifiable(ReservationStatus $status): self
    {
        return new self(sprintf(
            'A reservation in state "%s" cannot be modified.',
            $status->value,
        ));
    }
}
