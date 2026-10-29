<?php

declare(strict_types=1);

namespace App\Modules\Folio\Domain\Exceptions;

use RuntimeException;

/**
 * An entry on a business date the night audit closed.
 */
final class ClosedBusinessDateException extends RuntimeException
{
    public static function forDate(string $date): self
    {
        return new self(sprintf(
            'The business date %s is closed: it accepts no new entry.',
            $date,
        ));
    }
}
