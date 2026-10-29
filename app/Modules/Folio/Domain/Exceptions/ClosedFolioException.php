<?php

declare(strict_types=1);

namespace App\Modules\Folio\Domain\Exceptions;

use RuntimeException;

/**
 * An entry on a folio that was closed.
 */
final class ClosedFolioException extends RuntimeException
{
    public static function forFolio(int $folioId): self
    {
        return new self(sprintf('The folio %d is closed: it accepts no new entry.', $folioId));
    }
}
