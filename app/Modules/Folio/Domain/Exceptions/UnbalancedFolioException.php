<?php

declare(strict_types=1);

namespace App\Modules\Folio\Domain\Exceptions;

use RuntimeException;

/**
 * A reversal that goes beyond what can be reversed.
 */
final class UnbalancedFolioException extends RuntimeException
{
    public static function reversalTooLarge(int $entryId): self
    {
        return new self(sprintf(
            'The reversal of the entry %d is bigger than what is left to reverse.',
            $entryId,
        ));
    }

    public static function currencyMismatch(string $expected, string $given): self
    {
        return new self(sprintf(
            'The amounts of a folio must be in the same currency: %s and %s.',
            $expected,
            $given,
        ));
    }
}
