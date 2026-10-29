<?php

declare(strict_types=1);

namespace App\Modules\Folio\Domain;

/**
 * The state of a folio.
 */
enum FolioStatus: string
{
    case Open = 'open';

    case Closed = 'closed';

    public function acceptsEntries(): bool
    {
        return $this === self::Open;
    }
}
