<?php

declare(strict_types=1);

namespace App\Modules\Folio\Domain;

/**
 * The kind of an entry of the ledger.
 */
enum FolioEntryType: string
{
    /** What the guest owes: the room, the extras. */
    case Charge = 'charge';

    /** A tax on a charge. */
    case Tax = 'tax';

    /** What the guest paid. It lowers the balance. */
    case Payment = 'payment';

    /** What is given back to the guest. It lowers the balance. */
    case Refund = 'refund';

    /** A correction, in either direction. */
    case Adjustment = 'adjustment';

    /** The reversal of another entry. It lowers the balance. */
    case Reversal = 'reversal';

    /**
     * Whether the entry lowers the balance.
     */
    public function lowersTheBalance(): bool
    {
        return match ($this) {
            self::Charge, self::Tax => false,
            self::Payment, self::Refund, self::Reversal => true,
            self::Adjustment => false,
        };
    }
}
