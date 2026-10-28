<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\DTOs;

/**
 * What happened to an event of a billing gateway.
 */
enum BillingOutcome: string
{
    /** The event was handled. */
    case Handled = 'handled';

    /** The event was already handled: the provider delivered it twice. */
    case Replayed = 'replayed';

    /** The event says nothing to us (a status we do not know). */
    case Ignored = 'ignored';

    /** The payload does not come from the provider. */
    case Rejected = 'rejected';

    /** The organization of the event is unknown. */
    case Unknown = 'unknown';
}
